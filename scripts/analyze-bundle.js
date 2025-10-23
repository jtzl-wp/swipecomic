/**
 * JTZL_SwipeComic - Bundle Analysis Script
 *
 * @package
 * @copyright Copyright (c) 2025, JT. G.
 * @license   GPL-3.0+
 * @since     3.0.0
 */

/* eslint-disable no-console */

const fs = require('fs');
const path = require('path');

/**
 * Analyze bundle sizes and provide optimization recommendations.
 */
function analyzeBundles() {
	const buildDir = path.join(__dirname, '..', 'build');

	console.log('📊 Bundle Size Analysis\n');

	const files = [
		'swipecomic.js',
		'swipecomic-optimized.js',
		'swipecomic-external.js',
		'swipecomic-external-optimized.js',
	];

	const sizes = {};

	files.forEach((file) => {
		const filePath = path.join(buildDir, file);
		if (fs.existsSync(filePath)) {
			const stats = fs.statSync(filePath);
			sizes[file] = stats.size;
			console.log(`${file}: ${(stats.size / 1024).toFixed(2)}KB`);
		}
	});

	console.log('\n🎯 Optimization Results:');

	if (sizes['swipecomic.js'] && sizes['swipecomic-optimized.js']) {
		const original = sizes['swipecomic.js'];
		const optimized = sizes['swipecomic-optimized.js'];
		const savings = original - optimized;
		const percentage = ((savings / original) * 100).toFixed(1);

		console.log(
			`Single Bundle: ${savings > 0 ? 'Saved' : 'Increased by'} ${Math.abs(savings / 1024).toFixed(2)}KB (${percentage}%)`
		);
	}

	if (
		sizes['swipecomic-external.js'] &&
		sizes['swipecomic-external-optimized.js']
	) {
		const original = sizes['swipecomic-external.js'];
		const optimized = sizes['swipecomic-external-optimized.js'];
		const savings = original - optimized;
		const percentage = ((savings / original) * 100).toFixed(1);

		console.log(
			`External React Bundle: ${savings > 0 ? 'Saved' : 'Increased by'} ${Math.abs(savings / 1024).toFixed(2)}KB (${percentage}%)`
		);
	}

	// Check code-split bundles
	const optimizedDir = path.join(buildDir, 'optimized');
	if (fs.existsSync(optimizedDir)) {
		console.log('\n📦 Code-Split Bundles:');

		const mainFile = path.join(optimizedDir, 'main.js');
		const uiFile = path.join(optimizedDir, 'ui-components.js');

		if (fs.existsSync(mainFile)) {
			const mainSize = fs.statSync(mainFile).size;
			console.log(`main.js: ${(mainSize / 1024).toFixed(2)}KB`);
		}

		if (fs.existsSync(uiFile)) {
			const uiSize = fs.statSync(uiFile).size;
			console.log(`ui-components.js: ${(uiSize / 1024).toFixed(2)}KB`);
		}

		// Check for chunk files
		const chunksDir = path.join(optimizedDir, 'chunks');
		if (fs.existsSync(chunksDir)) {
			const chunks = fs.readdirSync(chunksDir);
			chunks.forEach((chunk) => {
				const chunkPath = path.join(chunksDir, chunk);
				const chunkSize = fs.statSync(chunkPath).size;
				console.log(`chunks/${chunk}: ${(chunkSize / 1024).toFixed(2)}KB`);
			});
		}
	}

	console.log('\n💡 Optimization Recommendations:');
	console.log(
		'• Use external React bundle for WordPress sites with React already loaded'
	);
	console.log('• Consider code-splitting for large applications');
	console.log('• Monitor bundle size regularly to prevent regression');
	console.log('• Use tree-shaking to remove unused code');
}

if (require.main === module) {
	analyzeBundles();
}

module.exports = { analyzeBundles };
