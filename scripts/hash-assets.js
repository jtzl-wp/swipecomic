/**
 * Asset Hash Generator
 *
 * Generates content-based hashes for CSS and admin assets.
 * This script processes assets that are not bundled through esbuild.
 *
 * @package
 * @since   2.0.0
 */

/* eslint-disable no-console */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

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

		// Pattern: filename.*.ext
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
						console.log(`🗑️  Cleaned up old file: ${file}`);
					} catch (unlinkError) {
						console.warn(
							`Failed to remove old file ${file}: ${unlinkError.message}`
						);
					}
				}
			}
		});
	} catch (error) {
		console.warn(`Failed to clean old files: ${error.message}`);
	}
}

/**
 * Read the asset manifest file.
 *
 * @return {Object} Manifest object or empty object if not found/invalid
 */
function readManifest() {
	const manifestPath = path.join(
		__dirname,
		'..',
		'build',
		'asset-manifest.json'
	);

	try {
		if (!fs.existsSync(manifestPath)) {
			return {};
		}

		const content = fs.readFileSync(manifestPath, 'utf8');
		const manifest = JSON.parse(content);

		if (typeof manifest !== 'object' || manifest === null) {
			console.warn('Invalid manifest structure, returning empty manifest');
			return {};
		}

		return manifest;
	} catch (error) {
		console.warn(`Failed to read manifest: ${error.message}`);
		return {};
	}
}

/**
 * Write manifest to disk.
 *
 * @param {Object} manifest - Manifest object to write
 * @return {void}
 */
function writeManifest(manifest) {
	const manifestPath = path.join(
		__dirname,
		'..',
		'build',
		'asset-manifest.json'
	);
	const buildDir = path.dirname(manifestPath);

	// Ensure build directory exists
	if (!fs.existsSync(buildDir)) {
		fs.mkdirSync(buildDir, { recursive: true });
	}

	// Write manifest
	fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');
}

/**
 * Update manifest with new asset mapping.
 *
 * @param {string} logicalName - Original filename
 * @param {string} hashedName  - Hashed filename
 * @return {void}
 */
function updateManifest(logicalName, hashedName) {
	const manifest = readManifest();
	manifest[logicalName] = hashedName;
	manifest.generated = new Date().toISOString();

	// Get version from package.json if available
	try {
		const packagePath = path.join(__dirname, '..', 'package.json');
		if (fs.existsSync(packagePath)) {
			const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
			if (packageJson.version) {
				manifest.version = packageJson.version;
			}
		}
	} catch (error) {
		console.warn(`Could not read version: ${error.message}`);
	}

	writeManifest(manifest);
}

/**
 * Process an asset file by adding hash and updating manifest.
 *
 * @param {string} filePath - Path to the asset file
 * @return {Promise<void>}
 */
async function hashFile(filePath) {
	try {
		if (!fs.existsSync(filePath)) {
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

		// Atomically copy and then remove to avoid data loss
		fs.copyFileSync(filePath, hashedPath);
		fs.unlinkSync(filePath);

		// Update manifest
		const logicalName = getLogicalName(filePath);
		const hashedName = getLogicalName(hashedPath);
		updateManifest(logicalName, hashedName);

		console.log(`📝 Hashed: ${logicalName} → ${hashedName}`);
	} catch (error) {
		console.error(`Failed to hash file ${filePath}: ${error.message}`);
		throw error;
	}
}

/**
 * Main function to hash CSS and admin assets.
 *
 * @return {Promise<void>}
 */
async function main() {
	try {
		console.log('🔧 Hashing CSS and admin assets...\n');

		// Hash CSS file
		const cssPath = path.join(__dirname, '..', 'build', 'swipecomic.css');
		if (fs.existsSync(cssPath)) {
			await hashFile(cssPath);
		}

		// Hash admin JavaScript
		const adminJsPath = path.join(
			__dirname,
			'..',
			'admin',
			'js',
			'swipecomic-admin.js'
		);
		if (fs.existsSync(adminJsPath)) {
			await hashFile(adminJsPath);
		}

		// Hash admin CSS
		const adminCssPath = path.join(
			__dirname,
			'..',
			'admin',
			'css',
			'swipecomic-admin.css'
		);
		if (fs.existsSync(adminCssPath)) {
			await hashFile(adminCssPath);
		}

		console.log('\n✅ Asset hashing completed successfully');
	} catch (error) {
		console.error('❌ Asset hashing failed:', error.message);
		process.exit(1);
	}
}

// Export functions for testing
module.exports = {
	hashFile,
	generateHash,
	generateHashedFilename,
	cleanOldFiles,
	getLogicalName,
	updateManifest,
};

// Run main function if this script is executed directly
if (require.main === module) {
	main();
}
