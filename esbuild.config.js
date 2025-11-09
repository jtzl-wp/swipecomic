/**
 * ESBuild Configuration for SwipeComic
 *
 * @package
 * @copyright Copyright (c) 2025, JT. G.
 * @license   GPL-3.0+
 * @since     2.0.0
 */

/* eslint-disable no-console */
/* eslint-disable @typescript-eslint/no-var-requires */

const fs = require('fs');
const path = require('path');

const { build } = require('esbuild');

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Generate asset manifest from esbuild metafile.
 *
 * @param {Object} metafile - ESBuild metafile object
 * @return {Object} Asset manifest mapping logical names to hashed names
 */
function generateManifest(metafile) {
	const manifest = {
		generated: new Date().toISOString(),
	};

	// Get version from package.json if available
	try {
		const packagePath = path.join(__dirname, 'package.json');
		if (fs.existsSync(packagePath)) {
			const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
			if (packageJson.version) {
				manifest.version = packageJson.version;
			}
		}
	} catch (error) {
		console.warn(`Could not read version: ${error.message}`);
	}

	// Process outputs from metafile
	if (metafile && metafile.outputs) {
		for (const [outputPath, outputMeta] of Object.entries(metafile.outputs)) {
			// Only process entry chunks (not shared chunks)
			if (outputMeta.entryPoint) {
				const hashedFilename = path.basename(outputPath);

				// Extract the logical name from the hashed output filename
				// The output follows the pattern: [entryName].[hash].[ext]
				// where entryName is the configured entry point name (e.g., 'swipecomic')
				const match = hashedFilename.match(/^(.+?)\.[A-Z0-9]+\.([^.]+)$/);
				if (match) {
					const [, name, ext] = match;
					const logicalName = `${name}.${ext}`;
					manifest[logicalName] = hashedFilename;
				}
			}
		}
	}

	return manifest;
}

/**
 * Write manifest to disk.
 *
 * @param {Object} manifest - Manifest object to write
 * @return {void}
 */
function writeManifest(manifest) {
	const manifestPath = path.join(__dirname, 'build', 'asset-manifest.json');
	const buildDir = path.dirname(manifestPath);

	// Ensure build directory exists
	if (!fs.existsSync(buildDir)) {
		fs.mkdirSync(buildDir, { recursive: true });
	}

	// Write manifest
	fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');
	console.log('📝 Generated asset manifest');
}

/**
 * Base configuration for all builds.
 */
const baseConfig = {
	bundle: true,
	platform: 'browser',
	target: 'es2019',
	jsx: 'automatic',
	resolveExtensions: ['.tsx', '.ts', '.jsx', '.js'],
	loader: {
		'.ts': 'ts',
		'.tsx': 'tsx',
	},
	define: {
		'process.env.NODE_ENV': JSON.stringify(
			process.env.NODE_ENV || 'production'
		),
	},
	splitting: true,
	metafile: true,
};

/**
 * Production configuration with React-safe optimization.
 */
const productionConfig = {
	...baseConfig,
	entryPoints: {
		swipecomic: 'src/index.tsx',
		'swipecomic-viewer': 'src/photoswipe-viewer.ts',
	},
	outdir: 'build',
	format: 'esm',
	entryNames: '[dir]/[name].[hash]',
	chunkNames: '[name]-[hash]',
	minify: true,
	treeShaking: true,
	drop: ['console', 'debugger'],
	legalComments: 'none',
	keepNames: true,
	minifyWhitespace: true,
	minifyIdentifiers: false,
	minifySyntax: true,
};

/**
 * Development configuration.
 */
const developmentConfig = {
	...baseConfig,
	entryPoints: {
		swipecomic: 'src/index.tsx',
		'swipecomic-viewer': 'src/photoswipe-viewer.ts',
	},
	outdir: 'build',
	format: 'esm',
	entryNames: '[dir]/[name].[hash]',
	chunkNames: '[name]-[hash]',
	sourcemap: true,
	minify: false,
};

/**
 * Development watch function.
 */
async function startDevWatch() {
	// eslint-disable-next-line @typescript-eslint/no-var-requires
	const { context } = require('esbuild');

	try {
		console.log('🔧 Starting development watch mode...');

		const watchConfig = {
			...developmentConfig,
			plugins: [
				{
					name: 'manifest-plugin',
					setup(buildInstance) {
						buildInstance.onEnd((result) => {
							if (result.errors.length === 0) {
								// Generate and write manifest from metafile
								if (result.metafile) {
									const manifest = generateManifest(result.metafile);
									writeManifest(manifest);
								}
								console.log('🔄 Build completed, manifest updated');
							} else {
								console.error('❌ Build failed:', result.errors);
							}
						});
					},
				},
			],
		};

		const ctx = await context(watchConfig);
		await ctx.watch();
		console.log('👀 Watching for changes...');

		process.on('SIGINT', async () => {
			console.log('\n🛑 Stopping watch mode...');
			await ctx.dispose();
			process.exit(0);
		});

		process.on('SIGTERM', async () => {
			console.log('\n🛑 Stopping watch mode...');
			await ctx.dispose();
			process.exit(0);
		});
	} catch (error) {
		console.error('❌ Watch mode failed:', error);
		process.exit(1);
	}
}

module.exports = {
	baseConfig,
	productionConfig,
	developmentConfig,
	startDevWatch,
};

/**
 * Build function for CLI usage.
 */
async function buildAll() {
	try {
		console.log('🚀 Building SwipeComic assets...\n');

		let result;
		if (isProduction) {
			console.log('📦 Building production bundle...');
			result = await build(productionConfig);
			console.log('✅ Production bundle complete\n');
		} else {
			console.log('🔧 Building development bundle...');
			result = await build(developmentConfig);
			console.log('✅ Development bundle complete\n');
		}

		// Generate and write manifest from metafile
		if (result.metafile) {
			const manifest = generateManifest(result.metafile);
			writeManifest(manifest);
		}

		console.log('🎉 All builds completed successfully!');
	} catch (error) {
		console.error('❌ Build failed:', error);
		process.exit(1);
	}
}

// Run build if called directly.
if (require.main === module) {
	buildAll();
}
