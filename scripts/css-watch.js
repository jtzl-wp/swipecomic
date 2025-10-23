/**
 * CSS Watch Mode with Hashing
 *
 * Watches CSS files and regenerates hashes when they change.
 * Used for development mode to maintain hash-based naming consistency.
 *
 * @package
 * @since   1.0.0
 */

const { spawn } = require('child_process');
const path = require('path');

const chokidar = require('chokidar');

const { hashCssFile } = require('./hash-css');

/**
 * Start CSS watch mode with hash generation.
 *
 * @return {void}
 */
function startCssWatch() {
	const cssPath = path.join(__dirname, '..', 'build', 'swipecomic.css');

	// eslint-disable-next-line no-console
	console.log('🎨 Starting CSS watch mode with hash generation...');

	// Start Tailwind CSS in watch mode
	const tailwindProcess = spawn(
		'npx',
		[
			'@tailwindcss/cli',
			'-i',
			'src/styles/globals.css',
			'-o',
			'build/swipecomic.css',
			'--watch',
		],
		{
			stdio: 'inherit',
			cwd: path.join(__dirname, '..'),
		}
	);

	// Watch for changes to the built CSS file using chokidar.
	const watcher = chokidar.watch(cssPath, {
		persistent: true,
		awaitWriteFinish: {
			stabilityThreshold: 300, // Wait for 300ms of no file changes.
			pollInterval: 100,
		},
	});

	watcher.on('add', async (filePath) => {
		try {
			await hashCssFile(filePath);
			// eslint-disable-next-line no-console
			console.log('🔄 CSS build completed, manifest updated');
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('❌ Error hashing CSS on initial build:', error.message);
		}
	});

	watcher.on('change', async (filePath) => {
		try {
			await hashCssFile(filePath);
			// eslint-disable-next-line no-console
			console.log('🔄 CSS build completed, manifest updated');
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('❌ Error hashing CSS in watch mode:', error.message);
		}
	});

	// Handle process termination.
	process.on('SIGINT', async () => {
		// eslint-disable-next-line no-console
		console.log('\n🛑 Stopping CSS watch mode...');

		// Stop watching file.
		await watcher.close();

		// Kill Tailwind process.
		tailwindProcess.kill('SIGINT');

		process.exit(0);
	});

	process.on('SIGTERM', async () => {
		await watcher.close();
		tailwindProcess.kill('SIGTERM');
		process.exit(0);
	});

	// Handle Tailwind process exit.
	tailwindProcess.on('exit', async (code) => {
		await watcher.close();
		if (code !== 0) {
			// eslint-disable-next-line no-console
			console.error(`Tailwind CSS process exited with code ${code}`);
			process.exit(code);
		}
	});
}

// Run if this script is executed directly
if (require.main === module) {
	startCssWatch();
}

module.exports = {
	startCssWatch,
};
