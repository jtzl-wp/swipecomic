/**
 * ESBuild Configuration for SwipeComic
 *
 * @package
 * @copyright Copyright (c) 2025, JT. G.
 * @license   GPL-3.0+
 * @since     1.0.0
 */

/* eslint-disable no-console */
/* eslint-disable @typescript-eslint/no-var-requires */

const { build } = require('esbuild');

const { hashPlugin } = require('./scripts/esbuild-hash-plugin');

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Base configuration for all builds.
 */
const baseConfig = {
	entryPoints: ['src/index.tsx'],
	bundle: true,
	platform: 'browser',
	target: 'es2019',
	jsx: 'automatic',
	resolveExtensions: ['.tsx', '.ts', '.jsx', '.js'],
	define: {
		'process.env.NODE_ENV': JSON.stringify(
			process.env.NODE_ENV || 'production'
		),
	},
};

/**
 * Production configuration with React-safe optimization.
 */
const productionConfig = {
	...baseConfig,
	outfile: 'build/swipecomic.js',
	format: 'iife',
	minify: true,
	treeShaking: true,
	drop: ['console', 'debugger'],
	legalComments: 'none',
	keepNames: true,
	minifyWhitespace: true,
	minifyIdentifiers: false,
	minifySyntax: true,
	plugins: [hashPlugin()],
};

/**
 * Development configuration.
 */
const developmentConfig = {
	...baseConfig,
	outfile: 'build/swipecomic.js',
	format: 'iife',
	sourcemap: true,
	minify: false,
	plugins: [hashPlugin()],
};

/**
 * Development watch function.
 */
async function startDevWatch() {
	// eslint-disable-next-line @typescript-eslint/no-var-requires
	const { context } = require('esbuild');

	try {
		console.log('🔧 Starting development watch mode with hash generation...');

		const watchConfig = {
			...developmentConfig,
			plugins: [
				{
					name: 'watch-hash-plugin',
					setup(buildInstance) {
						const originalHashPlugin = hashPlugin();
						originalHashPlugin.setup(buildInstance);

						buildInstance.onEnd((result) => {
							if (result.errors.length === 0) {
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
		console.log('👀 Watching for changes (with hash generation)...');

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

		if (isProduction) {
			console.log('📦 Building production bundle...');
			await build(productionConfig);
			console.log('✅ Production bundle complete\n');
		} else {
			console.log('🔧 Building development bundle...');
			await build(developmentConfig);
			console.log('✅ Development bundle complete\n');
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
