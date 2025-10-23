/**
 * CSS Hash Generator
 *
 * Generates content-based hashes for CSS files and manages
 * file renaming, manifest updates, and cleanup of old hashed files.
 *
 * This script is designed to run after CSS build processes (like Tailwind CSS)
 * to add hash-based naming for cache invalidation. It works similarly to the
 * esbuild hash plugin but operates as a standalone script for CSS files.
 *
 * The script:
 * 1. Reads the built CSS file content
 * 2. Generates a SHA-256 hash (8 characters)
 * 3. Renames the file to include the hash (e.g., swipecomic.css -> swipecomic.a3f2b1c9.css)
 * 4. Updates the asset manifest with the new mapping
 * 5. Cleans up old hashed CSS files from previous builds
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
 * Uses SHA-256 algorithm and takes the first 8 characters for consistency
 * with the JavaScript hash plugin. This ensures all assets use the same
 * hashing approach and hash length.
 *
 * @param {string} content - CSS file content to hash
 * @return {string} 8-character hexadecimal hash
 * @example
 * const hash = generateHash('.btn { color: blue; }');
 * // Returns something like: "d4e5f6a7"
 */
function generateHash(content) {
	return crypto.createHash('sha256').update(content).digest('hex').slice(0, 8);
}

/**
 * Get the logical name from a file path.
 * Converts paths like 'build/swipecomic.css' to 'swipecomic.css'
 *
 * @param {string} filePath - Full file path
 * @return {string} Logical name for manifest
 */
function getLogicalName(filePath) {
	return path.basename(filePath);
}

/**
 * Generate hashed filename from original filename and hash.
 *
 * @param {string} originalPath - Original file path
 * @param {string} hash         - Content hash
 * @return {string} Hashed filename
 */
function generateHashedFilename(originalPath, hash) {
	const dir = path.dirname(originalPath);
	const ext = path.extname(originalPath);
	const basename = path.basename(originalPath, ext);

	return path.join(dir, `${basename}.${hash}${ext}`);
}

/**
 * Find and remove old hashed CSS files matching the pattern.
 *
 * @param {string} originalPath - Original file path (e.g., 'build/swipecomic.css')
 * @param {string} currentHash  - Current hash to preserve
 * @return {void}
 */
function cleanOldFiles(originalPath, currentHash) {
	try {
		const dir = path.dirname(originalPath);
		const ext = path.extname(originalPath);
		const basename = path.basename(originalPath, ext);

		// Pattern: swipecomic.*.css
		const pattern = new RegExp(
			`^${basename}\\.[a-f0-9]{8}${ext.replace('.', '\\.')}`
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
						console.log(`🗑️  Cleaned up old CSS file: ${file}`);
					} catch (unlinkError) {
						// eslint-disable-next-line no-console
						console.warn(
							`Failed to remove old CSS file ${file}: ${unlinkError.message}`
						);
					}
				}
			}
		});
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(`Failed to clean old CSS files: ${error.message}`);
	}
}

/**
 * Process a CSS file by adding hash and updating manifest.
 *
 * This is the main processing function that orchestrates the entire
 * CSS hash-based naming workflow. It mirrors the functionality of the
 * JavaScript hash plugin but operates on CSS files.
 *
 * @param {string} cssPath - Path to the CSS file to process
 * @return {Promise<void>}
 * @throws {Error} If CSS file processing fails
 * @example
 * await hashCssFile('build/swipecomic.css');
 * // Results in: build/swipecomic.a3f2b1c9.css + manifest update
 */
async function hashCssFile(cssPath) {
	try {
		if (!fs.existsSync(cssPath)) {
			// eslint-disable-next-line no-console
			console.warn(`CSS file not found for hashing: ${cssPath}`);
			return;
		}

		// Read CSS file content
		const content = fs.readFileSync(cssPath, 'utf8');

		// Generate hash
		const hash = generateHash(content);

		// Generate hashed filename
		const hashedPath = generateHashedFilename(cssPath, hash);

		// Clean old CSS files before creating new one
		cleanOldFiles(cssPath, hash);

		// Rename file to include hash
		fs.renameSync(cssPath, hashedPath);

		// Update manifest
		const logicalName = getLogicalName(cssPath);
		const hashedName = getLogicalName(hashedPath);
		await updateManifest(logicalName, hashedName);

		// eslint-disable-next-line no-console
		console.log(`🎨 Hashed CSS: ${logicalName} → ${hashedName}`);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to hash CSS file ${cssPath}: ${error.message}`);
		throw error;
	}
}

/**
 * Main function to hash CSS file from command line.
 *
 * Provides a command-line interface for CSS hashing. Can be called
 * with an optional file path argument, otherwise defaults to the
 * standard build/swipecomic.css location.
 *
 * Usage:
 * - `node scripts/hash-css.js` (uses default path)
 * - `node scripts/hash-css.js path/to/custom.css` (uses custom path)
 *
 * @return {Promise<void>}
 * @throws {Error} Exits with code 1 if hashing fails
 */
async function main() {
	try {
		// Default CSS file path
		const cssPath = path.join(__dirname, '..', 'build', 'swipecomic.css');

		// Allow override via command line argument
		const targetPath = process.argv[2] || cssPath;

		await hashCssFile(targetPath);

		// eslint-disable-next-line no-console
		console.log('✅ CSS hashing completed successfully');
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('❌ CSS hashing failed:', error.message);
		process.exit(1);
	}
}

// Export functions for testing
module.exports = {
	hashCssFile,
	generateHash,
	generateHashedFilename,
	cleanOldFiles,
	getLogicalName,
};

// Run main function if this script is executed directly
if (require.main === module) {
	main();
}
