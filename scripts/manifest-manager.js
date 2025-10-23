/**
 * Asset Manifest Manager
 *
 * Provides utilities for reading, updating, and writing the asset manifest JSON file
 * with atomic operations and proper error handling.
 *
 * @package
 * @since   1.0.0
 */

const fs = require('fs');
const path = require('path');

// Constants
const MANIFEST_PATH = path.join(
	__dirname,
	'..',
	'build',
	'asset-manifest.json'
);
const TEMP_SUFFIX = '.tmp';
const LOCK_SUFFIX = '.lock';

/**
 * Read the asset manifest file.
 *
 * @return {Object} Manifest object or empty object if not found/invalid
 */
function readManifest() {
	try {
		if (!fs.existsSync(MANIFEST_PATH)) {
			return {};
		}

		const content = fs.readFileSync(MANIFEST_PATH, 'utf8');
		const manifest = JSON.parse(content);

		// Validate manifest structure.
		if (typeof manifest !== 'object' || manifest === null) {
			// eslint-disable-next-line no-console
			console.warn('Invalid manifest structure, returning empty manifest');
			return {};
		}

		return manifest;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(`Failed to read manifest: ${error.message}`);
		return {};
	}
}

/**
 * Update manifest with new asset mapping.
 *
 * @param {string} logicalName - Original filename (e.g., "swipecomic.js")
 * @param {string} hashedName  - Hashed filename (e.g., "swipecomic.a3f2b1c.js")
 * @return {Promise<void>}
 */
async function updateManifest(logicalName, hashedName) {
	try {
		// Acquire lock to prevent concurrent writes.
		const lockPath = MANIFEST_PATH + LOCK_SUFFIX;
		const lockAcquired = await acquireLock(lockPath);

		if (!lockAcquired) {
			throw new Error('Could not acquire manifest lock');
		}

		try {
			// Read current manifest.
			const manifest = readManifest();

			// Update with new mapping.
			manifest[logicalName] = hashedName;
			manifest.generated = new Date().toISOString();

			// Get version from package.json if available.
			try {
				const packagePath = path.join(__dirname, '..', 'package.json');
				if (fs.existsSync(packagePath)) {
					const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
					if (packageJson.version) {
						manifest.version = packageJson.version;
					}
				}
			} catch (versionError) {
				// Version is optional, continue without it.
				// eslint-disable-next-line no-console
				console.warn(`Could not read version: ${versionError.message}`);
			}

			// Write manifest atomically.
			writeManifest(manifest);
		} finally {
			// Always release lock.
			releaseLock(lockPath);
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to update manifest: ${error.message}`);
		throw error;
	}
}

/**
 * Write manifest to disk with atomic operation.
 *
 * @param {Object} manifest - Complete manifest object
 * @return {void}
 */
function writeManifest(manifest) {
	try {
		// Ensure build directory exists.
		const buildDir = path.dirname(MANIFEST_PATH);
		if (!fs.existsSync(buildDir)) {
			fs.mkdirSync(buildDir, { recursive: true });
		}

		// Write to temporary file first.
		const tempPath = MANIFEST_PATH + TEMP_SUFFIX;
		const manifestJson = JSON.stringify(manifest, null, 2);

		fs.writeFileSync(tempPath, manifestJson, 'utf8');

		// Atomic rename to final location.
		fs.renameSync(tempPath, MANIFEST_PATH);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to write manifest: ${error.message}`);

		// Clean up temp file if it exists.
		const tempPath = MANIFEST_PATH + TEMP_SUFFIX;
		if (fs.existsSync(tempPath)) {
			try {
				fs.unlinkSync(tempPath);
			} catch (cleanupError) {
				// eslint-disable-next-line no-console
				console.warn(`Failed to cleanup temp file: ${cleanupError.message}`);
			}
		}

		throw error;
	}
}

/**
 * Acquire a file lock to prevent concurrent writes.
 *
 * @param {string} lockPath   - Path to lock file
 * @param {number} maxRetries - Maximum number of retry attempts
 * @param {number} retryDelay - Delay between retries in milliseconds
 * @return {Promise<boolean>} True if lock acquired, false otherwise
 */
async function acquireLock(lockPath, maxRetries = 10, retryDelay = 100) {
	for (let i = 0; i < maxRetries; i++) {
		try {
			// Try to create lock file exclusively.
			fs.writeFileSync(lockPath, process.pid.toString(), {
				flag: 'wx',
			});
			return true;
		} catch (error) {
			if (error.code === 'EEXIST') {
				// Lock file exists, check if process is still running.
				try {
					const lockPid = parseInt(fs.readFileSync(lockPath, 'utf8'), 10);

					// Check if process is still running.
					try {
						process.kill(lockPid, 0); // Signal 0 just checks if process exists.
						// Process exists, wait and retry.
						if (i < maxRetries - 1) {
							// Use an async sleep instead of a busy-wait loop.
							await new Promise((resolve) => setTimeout(resolve, retryDelay));
							continue;
						}
					} catch (killError) {
						// Process doesn't exist, remove stale lock.
						try {
							fs.unlinkSync(lockPath);
							continue; // Try again.
						} catch (unlinkError) {
							// eslint-disable-next-line no-console
							console.warn(
								`Failed to remove stale lock: ${unlinkError.message}`
							);
						}
					}
				} catch (readError) {
					// Invalid lock file, try to remove it.
					try {
						fs.unlinkSync(lockPath);
						continue; // Try again.
					} catch (unlinkError) {
						// eslint-disable-next-line no-console
						console.warn(
							`Failed to remove invalid lock: ${unlinkError.message}`
						);
					}
				}
			} else {
				// eslint-disable-next-line no-console
				console.error(`Failed to acquire lock: ${error.message}`);
				break;
			}
		}
	}

	return false;
}

/**
 * Release a file lock.
 *
 * @param {string} lockPath - Path to lock file
 * @return {void}
 */
function releaseLock(lockPath) {
	try {
		if (fs.existsSync(lockPath)) {
			fs.unlinkSync(lockPath);
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(`Failed to release lock: ${error.message}`);
	}
}

/**
 * Get hashed filename from manifest.
 *
 * @param {string} logicalName - Logical asset name (e.g., 'swipecomic.js')
 * @return {string|null} Hashed filename or null if not found
 */
function getHashedFilename(logicalName) {
	try {
		const manifest = readManifest();
		return manifest[logicalName] || null;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(
			`Failed to get hashed filename for ${logicalName}: ${error.message}`
		);
		return null;
	}
}

/**
 * Check if manifest exists and is valid.
 *
 * @return {boolean} True if manifest exists and is valid
 */
function isManifestValid() {
	try {
		const manifest = readManifest();
		return Object.keys(manifest).length > 0;
	} catch (error) {
		return false;
	}
}

/**
 * Clear the manifest file.
 *
 * @return {void}
 */
function clearManifest() {
	try {
		if (fs.existsSync(MANIFEST_PATH)) {
			fs.unlinkSync(MANIFEST_PATH);
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(`Failed to clear manifest: ${error.message}`);
		throw error;
	}
}

module.exports = {
	readManifest,
	updateManifest,
	writeManifest,
	getHashedFilename,
	isManifestValid,
	clearManifest,
	MANIFEST_PATH,
};
