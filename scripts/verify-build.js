/**
 * Build Verification Script
 *
 * Verifies the integrity of the build process by checking:
 * - Asset manifest exists and is valid JSON
 * - All files referenced in manifest actually exist
 * - Manifest contains expected keys and structure
 *
 * @package
 * @since   1.0.0
 */

/* eslint-disable no-console */

const fs = require('fs');
const path = require('path');

// Exit codes
const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;

// Manifest path
const MANIFEST_PATH = path.join(
	__dirname,
	'..',
	'build',
	'asset-manifest.json'
);

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

		// Validate manifest structure
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

// Expected asset types that should be in the manifest
const EXPECTED_ASSETS = [
	'swipecomic.js',
	'swipecomic-viewer.js',
	'swipecomic.css',
];

// Optional assets that may be present
const OPTIONAL_ASSETS = [];

/**
 * Main verification function.
 *
 * @return {Promise<void>}
 */
async function verifyBuild() {
	console.log('🔍 Starting build verification...\n');

	let hasErrors = false;

	try {
		// Step 1: Check if manifest exists
		if (!fs.existsSync(MANIFEST_PATH)) {
			console.error('❌ Asset manifest not found at:', MANIFEST_PATH);
			return process.exit(EXIT_FAILURE);
		}

		console.log('✅ Asset manifest found');

		// Step 2: Read and validate manifest JSON
		const manifest = readManifest();

		if (!manifest || Object.keys(manifest).length === 0) {
			console.error('❌ Asset manifest is empty or invalid');
			return process.exit(EXIT_FAILURE);
		}

		console.log('✅ Asset manifest is valid JSON');

		// Step 3: Check manifest structure
		const structureValid = validateManifestStructure(manifest);
		if (!structureValid) {
			hasErrors = true;
		}

		// Step 4: Check required assets are present in manifest
		const assetsValid = validateRequiredAssets(manifest);
		if (!assetsValid) {
			hasErrors = true;
		}

		// Step 5: Verify all referenced files exist
		const filesExist = await verifyReferencedFiles(manifest);
		if (!filesExist) {
			hasErrors = true;
		}

		// Step 6: Check for orphaned hashed files
		const noOrphans = await checkForOrphanedFiles(manifest);
		if (!noOrphans) {
			hasErrors = true;
		}

		// Final result
		if (hasErrors) {
			console.log('\n❌ Build verification failed with errors');
			process.exit(EXIT_FAILURE);
		} else {
			console.log('\n✅ Build verification completed successfully');
			console.log('📊 Manifest summary:');
			printManifestSummary(manifest);
			process.exit(EXIT_SUCCESS);
		}
	} catch (error) {
		console.error(
			'❌ Build verification failed with exception:',
			error.message
		);
		process.exit(EXIT_FAILURE);
	}
}

/**
 * Validate manifest structure and metadata.
 *
 * @param {Object} manifest - The manifest object
 * @return {boolean} True if structure is valid
 */
function validateManifestStructure(manifest) {
	let isValid = true;

	// Check for generated timestamp
	if (!manifest.generated) {
		console.warn('⚠️  Manifest missing "generated" timestamp');
		isValid = false;
	} else {
		try {
			const date = new Date(manifest.generated);
			if (isNaN(date.getTime())) {
				console.error('❌ Invalid "generated" timestamp format');
				isValid = false;
			} else {
				console.log('✅ Generated timestamp is valid:', manifest.generated);
			}
		} catch (error) {
			console.error('❌ Error parsing "generated" timestamp:', error.message);
			isValid = false;
		}
	}

	// Check for version (optional but recommended)
	if (!manifest.version) {
		console.warn('⚠️  Manifest missing "version" field (optional)');
	} else {
		console.log('✅ Version found:', manifest.version);
	}

	return isValid;
}

/**
 * Validate that required assets are present in manifest.
 *
 * @param {Object} manifest - The manifest object
 * @return {boolean} True if all required assets are present
 */
function validateRequiredAssets(manifest) {
	let isValid = true;

	console.log('\n📋 Checking required assets...');

	for (const asset of EXPECTED_ASSETS) {
		if (!manifest[asset]) {
			console.error(`❌ Required asset missing from manifest: ${asset}`);
			isValid = false;
		} else {
			console.log(`✅ Required asset found: ${asset} -> ${manifest[asset]}`);
		}
	}

	// Check optional assets
	console.log('\n📋 Checking optional assets...');
	for (const asset of OPTIONAL_ASSETS) {
		if (manifest[asset]) {
			console.log(`✅ Optional asset found: ${asset} -> ${manifest[asset]}`);
		} else {
			console.log(`ℹ️  Optional asset not present: ${asset}`);
		}
	}

	return isValid;
}

/**
 * Verify that all files referenced in manifest actually exist.
 *
 * @param {Object} manifest - The manifest object
 * @return {Promise<boolean>} True if all files exist
 */
async function verifyReferencedFiles(manifest) {
	let allExist = true;
	const buildDir = path.dirname(MANIFEST_PATH);
	const projectRoot = path.dirname(buildDir);

	console.log('\n📁 Verifying referenced files exist...');

	for (const [logicalName, hashedName] of Object.entries(manifest)) {
		// Skip metadata fields
		if (logicalName === 'generated' || logicalName === 'version') {
			continue;
		}

		// Determine the correct directory based on file type
		let filePath;
		if (hashedName.includes('admin') && hashedName.endsWith('.js')) {
			filePath = path.join(projectRoot, 'admin', 'js', hashedName);
		} else if (hashedName.includes('admin') && hashedName.endsWith('.css')) {
			filePath = path.join(projectRoot, 'admin', 'css', hashedName);
		} else {
			filePath = path.join(buildDir, hashedName);
		}

		if (!fs.existsSync(filePath)) {
			console.error(`❌ Referenced file does not exist: ${hashedName}`);
			console.error(`   Expected at: ${filePath}`);
			allExist = false;
		} else {
			// Get file stats for additional info
			const stats = fs.statSync(filePath);
			const sizeKB = Math.round((stats.size / 1024) * 100) / 100;
			console.log(`✅ File exists: ${hashedName} (${sizeKB} KB)`);
		}
	}

	return allExist;
}

/**
 * Check for orphaned hashed files that aren't in the manifest.
 *
 * @param {Object} manifest - The manifest object
 * @return {Promise<boolean>} True if no problematic orphans found
 */
async function checkForOrphanedFiles(manifest) {
	const buildDir = path.dirname(MANIFEST_PATH);
	let isClean = true;

	console.log('\n🧹 Checking for orphaned hashed files...');

	try {
		const files = fs.readdirSync(buildDir);
		const manifestFiles = new Set(
			Object.values(manifest).filter((v) => typeof v === 'string')
		);

		// Patterns for hashed files (esbuild uses uppercase hash)
		const hashedPatterns = [
			/^swipecomic-viewer\.[A-Z0-9]+\.js$/,
			/^swipecomic\.[A-Z0-9]+\.js$/,
		];

		for (const file of files) {
			// Check if this looks like a hashed file
			const isHashedFile = hashedPatterns.some((pattern) => pattern.test(file));

			if (isHashedFile && !manifestFiles.has(file)) {
				console.warn(`⚠️  Orphaned hashed file found: ${file}`);
				console.warn(
					'   This file is not referenced in the manifest and may be from a previous build'
				);
				// Note: We don't mark this as an error since orphaned files from previous builds
				// are expected during development. The cleanup process should handle them.
			}
		}

		console.log('✅ Orphaned file check completed');
	} catch (error) {
		console.error('❌ Error checking for orphaned files:', error.message);
		isClean = false;
	}

	return isClean;
}

/**
 * Print a summary of the manifest contents.
 *
 * @param {Object} manifest - The manifest object
 * @return {void}
 */
function printManifestSummary(manifest) {
	const assetCount = Object.keys(manifest).filter(
		(key) => key !== 'generated' && key !== 'version'
	).length;

	console.log(`   Assets: ${assetCount}`);
	console.log(`   Generated: ${manifest.generated || 'Unknown'}`);
	console.log(`   Version: ${manifest.version || 'Unknown'}`);

	// Show asset mappings
	console.log('   Mappings:');
	for (const [logical, hashed] of Object.entries(manifest)) {
		if (logical !== 'generated' && logical !== 'version') {
			console.log(`     ${logical} -> ${hashed}`);
		}
	}
}

/**
 * Show usage information.
 *
 * @return {void}
 */
function showUsage() {
	console.log('Usage: node scripts/verify-build.js');
	console.log('');
	console.log('Verifies the integrity of the build process by checking:');
	console.log('- Asset manifest exists and is valid JSON');
	console.log('- All files referenced in manifest actually exist');
	console.log('- Manifest contains expected keys and structure');
	console.log('');
	console.log('Exit codes:');
	console.log('  0 - Success');
	console.log('  1 - Failure');
}

// Handle command line arguments
if (process.argv.includes('--help') || process.argv.includes('-h')) {
	showUsage();
	process.exit(EXIT_SUCCESS);
}

// Run verification if this script is executed directly
if (require.main === module) {
	verifyBuild();
}

module.exports = {
	verifyBuild,
	validateManifestStructure,
	validateRequiredAssets,
	verifyReferencedFiles,
	checkForOrphanedFiles,
};
