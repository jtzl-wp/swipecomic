/**
 * Notification Utilities
 *
 * Centralized notification system for displaying user-friendly error messages
 */

/**
 * Show error notification to user
 *
 * @param message  - Error message to display
 * @param duration - Duration in milliseconds before auto-hiding (default: 4000ms)
 */
export function showErrorNotification(message: string, duration = 4000): void {
	// Create error notification element
	const notification = document.createElement('div');
	notification.className = 'swipecomic-error-notification';
	notification.textContent = message;

	// Add to DOM
	document.body.appendChild(notification);

	// Show notification
	setTimeout(() => {
		notification.classList.add('visible');
	}, 10);

	// Auto-hide after specified duration
	setTimeout(() => {
		notification.classList.remove('visible');
		setTimeout(() => {
			notification.remove();
		}, 300); // Corresponds to CSS transition time
	}, duration);
}
