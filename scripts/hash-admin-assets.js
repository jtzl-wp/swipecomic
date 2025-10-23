/**
 * Admin Assets Hash Generator
 *
 * Generates content-based hashes for admin JavaScript and CSS files.
 * This script processes admin assets separately from the main build pipeline
 * since they are not bundled through esbuild.
 *
 * The script:
 * 1. Reads admin JS and CSS files
 * 2. Generates SHA-256 hashes (8 characters)
 * 3. Renames files to include the hash
 * 4. Updates the asset manifest
 * 5. Cleans up old hashed files
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
 * @param {string} content - File content to hash
 * @return {string} 8-character hexadecimal hash
 */
function generateHash(content) {
	return crypto.createHash('sha256').update(content).digest('hex').slice(0, 8);
}

/**
 * Get the logical name from a file path.
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
 * Find and remove old hashed files matching the pattern.
 *
 * @param {string} originalPath - Original file path
 * @param {string} currentHash  - Current hash to preserve
 * @return {void}
 */
function cleanOldFiles(originalPath, currentHash) {
	try {
		const dir = path.dirname(originalPath);
		const ext = path.extname(originalPath);
		const basename = path.basename(originalPath, ext);

		// Pattern: swipecomic-admin.*.js or swipecomic-admin.*.css
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
						console.log(`🗑️  Cleaned up old admin file: ${file}`);
					} catch (unlinkError) {
						// eslint-disable-next-line no-console
						console.warn(
							`Failed to remove old admin file ${file}: ${unlinkError.message}`
						);
					}
				}
			}
		});
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(`Failed to clean old admin files: ${error.message}`);
	}
}

/**
 * Process an admin asset file by adding hash and updating manifest.
 *
 * @param {string} filePath - Path to the admin asset file
 * @return {Promise<void>}
 */
async function hashAdminFile(filePath) {
	try {
		if (!fs.existsSync(filePath)) {
			// eslint-disable-next-line no-console
			console.warn(`Admin file not found for hashing: ${filePath}`);
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

		// Copy file to hashed version (keep original)
		fs.copyFileSync(filePath, hashedPath);

		// Update manifest
		const logicalName = getLogicalName(filePath);
		const hashedName = getLogicalName(hashedPath);
		await updateManifest(logicalName, hashedName);

		// eslint-disable-next-line no-console
		console.log(`📝 Hashed admin asset: ${logicalName} → ${hashedName}`);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to hash admin file ${filePath}: ${error.message}`);
		throw error;
	}
}

/**
 * Main function to hash all admin assets.
 *
 * @return {Promise<void>}
 */
async function main() {
	try {
		// eslint-disable-next-line no-console
		console.log('🔧 Hashing admin assets...\n');

		const adminJsPath = path.join(
			__dirname,
			'..',
			'admin',
			'js',
			'swipecomic-admin.js'
		);
		const adminCssPath = path.join(
			__dirname,
			'..',
			'admin',
			'css',
			'swipecomic-admin.css'
		);

		// Hash admin JavaScript
		await hashAdminFile(adminJsPath);

		// Hash admin CSS
		await hashAdminFile(adminCssPath);

		// eslint-disable-next-line no-console
		console.log('\n✅ Admin assets hashing completed successfully');
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('❌ Admin assets hashing failed:', error.message);
		process.exit(1);
	}
}

// Export functions for testing
module.exports = {
	hashAdminFile,
	generateHash,
	generateHashedFilename,
	cleanOldFiles,
	getLogicalName,
};

// Run main function if this script is executed directly
if (require.main === module) {
	main();
}
