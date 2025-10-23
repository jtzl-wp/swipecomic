/**
 * ESBuild Hash Plugin
 *
 * Generates content-based hashes for JavaScript bundles and manages
 * file renaming, manifest updates, and cleanup of old hashed files.
 *
 * This plugin implements hash-based asset naming to solve browser caching issues
 * by ensuring that any code change results in a new filename, guaranteeing cache
 * invalidation across all caching layers (browsers, CDNs, WordPress caching plugins).
 *
 * The plugin works by:
 * 1. Generating SHA-256 hashes from file content (8 characters)
 * 2. Renaming files from swipecomic.js to swipecomic.[hash].js
 * 3. Updating the asset manifest with logical name -> hashed name mappings
 * 4. Cleaning up old hashed files from previous builds
 *
 * @package
 * @since   1.0.0
 */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const { updateManifest } = require('./manifest-manager');

/**
 * Generate content-based hash from file content.
 *
 * Uses SHA-256 algorithm and takes the first 8 characters for a good balance
 * between uniqueness and filename length. This provides 16^8 = 4.3 billion
 * possible combinations, which is more than sufficient for this use case.
 *
 * @param {string} content - File content to hash
 * @return {string} 8-character hexadecimal hash
 * @example
 * const hash = generateHash('console.log("hello");');
 * // Returns something like: "a3f2b1c9"
 */
function generateHash(content) {
	return crypto.createHash('sha256').update(content).digest('hex').slice(0, 8);
}

/**
 * Get the logical name from a file path.
 *
 * Converts full paths to logical names used in the manifest.
 * This allows the manifest to use consistent keys regardless of
 * the actual build directory structure.
 *
 * @param {string} filePath - Full file path
 * @return {string} Logical name for manifest (just the filename)
 * @example
 * getLogicalName('build/swipecomic.js') // Returns: 'swipecomic.js'
 * getLogicalName('/path/to/build/swipecomic.css') // Returns: 'swipecomic.css'
 */
function getLogicalName(filePath) {
	return path.basename(filePath);
}

/**
 * Generate hashed filename from original filename and hash.
 *
 * Inserts the hash before the file extension, maintaining the directory
 * structure. This ensures the hashed file is created in the same location
 * as the original file.
 *
 * @param {string} originalPath - Original file path
 * @param {string} hash         - Content hash (8 characters)
 * @return {string} Hashed filename with full path
 * @example
 * generateHashedFilename('build/swipecomic.js', 'a3f2b1c9')
 * // Returns: 'build/swipecomic.a3f2b1c9.js'
 */
function generateHashedFilename(originalPath, hash) {
	const dir = path.dirname(originalPath);
	const ext = path.extname(originalPath);
	const basename = path.basename(originalPath, ext);

	return path.join(dir, `${basename}.${hash}${ext}`);
}

/**
 * Find and remove old hashed files matching the pattern.
 *
 * Cleans up files from previous builds to prevent the build directory
 * from accumulating old hashed files. Only removes files that match
 * the expected hash pattern and are not the current hash.
 *
 * @param {string} originalPath - Original file path (e.g., 'build/swipecomic.js')
 * @param {string} currentHash  - Current hash to preserve (8 characters)
 * @return {void}
 * @example
 * // If build directory contains:
 * // - swipecomic.a1b2c3d4.js (old)
 * // - swipecomic.e5f6g7h8.js (old)
 * // And currentHash is 'x9y8z7w6'
 * // This will remove the old files but preserve the new one
 */
function cleanOldFiles(originalPath, currentHash) {
	try {
		const dir = path.dirname(originalPath);
		const ext = path.extname(originalPath);
		const basename = path.basename(originalPath, ext);

		// Pattern: swipecomic.*.js
		const pattern = new RegExp(
			`^${basename}\\.[a-f0-9]{8}${ext.replace('.', '\\.')}$`
		);

		if (!fs.existsSync(dir)) {
			return;
		}

		const files = fs.readdirSync(dir);

		files.forEach((file) => {
			if (pattern.test(file)) {
				// Extract hash from filename
				const fileHash = file.replace(basename + '.', '').replace(ext, '');

				// Only remove if it's not the current hash
				if (fileHash !== currentHash) {
					const filePath = path.join(dir, file);
					try {
						fs.unlinkSync(filePath);
						// eslint-disable-next-line no-console
						console.log(`🗑️  Cleaned up old file: ${file}`);
					} catch (unlinkError) {
						// eslint-disable-next-line no-console
						console.warn(
							`Failed to remove old file ${file}: ${unlinkError.message}`
						);
					}
				}
			}
		});
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(`Failed to clean old files: ${error.message}`);
	}
}

/**
 * Process a built file by adding hash and updating manifest.
 *
 * This is the main processing function that orchestrates the entire
 * hash-based naming workflow:
 * 1. Reads the file content
 * 2. Generates a content-based hash
 * 3. Cleans up old hashed files
 * 4. Renames the file to include the hash
 * 5. Updates the asset manifest
 *
 * @param {string} filePath - Path to the built file
 * @return {Promise<void>}
 * @throws {Error} If file processing fails
 */
async function processFile(filePath) {
	try {
		if (!fs.existsSync(filePath)) {
			// eslint-disable-next-line no-console
			console.warn(`File not found for hashing: ${filePath}`);
			return;
		}

		// Read file content
		const content = fs.readFileSync(filePath, 'utf8');

		// Generate hash
		const hash = generateHash(content);

		// Generate hashed filename
		const hashedPath = generateHashedFilename(filePath, hash);

		// Clean old files before creating new one
		cleanOldFiles(filePath, hash);

		// Rename file to include hash
		fs.renameSync(filePath, hashedPath);

		// Update manifest
		const logicalName = getLogicalName(filePath);
		const hashedName = getLogicalName(hashedPath);
		await updateManifest(logicalName, hashedName);

		// eslint-disable-next-line no-console
		console.log(`📝 Hashed: ${logicalName} → ${hashedName}`);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to process file ${filePath}: ${error.message}`);
		throw error;
	}
}

/**
 * ESBuild plugin for hash-based asset naming.
 *
 * Creates an ESBuild plugin that hooks into the build process to automatically
 * generate hashed filenames for JavaScript bundles. The plugin runs after the
 * build completes successfully and processes the output file.
 *
 * Usage in esbuild config:
 * ```javascript
 * const { hashPlugin } = require('./scripts/esbuild-hash-plugin');
 *
 * esbuild.build({
 *   // ... other options
 *   plugins: [hashPlugin()],
 * });
 * ```
 *
 * @return {Object} ESBuild plugin object with name and setup function
 */
function hashPlugin() {
	return {
		name: 'hash-plugin',
		setup(build) {
			// Hook into build end to rename files and update manifest
			build.onEnd(async (result) => {
				// Only process if build was successful
				if (result.errors.length > 0) {
					// eslint-disable-next-line no-console
					console.log('⚠️  Skipping hash generation due to build errors');
					return;
				}

				try {
					// Get the output file path from build options
					const outfile = build.initialOptions.outfile;

					if (outfile) {
						await processFile(outfile);
					} else {
						// eslint-disable-next-line no-console
						console.warn(
							'No outfile specified in build options, skipping hash generation'
						);
					}
				} catch (error) {
					// eslint-disable-next-line no-console
					console.error('Hash plugin error:', error.message);
					// Don't fail the build, just log the error
				}
			});
		},
	};
}

module.exports = {
	hashPlugin,
	generateHash,
	generateHashedFilename,
	cleanOldFiles,
	processFile,
	getLogicalName,
};
