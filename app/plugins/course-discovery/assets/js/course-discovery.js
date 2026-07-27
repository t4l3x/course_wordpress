(() => {
	'use strict';

	const mobileQuery = window.matchMedia('(max-width: 47.99rem)');

	const initialize = (root) => {
		const openButton = root.querySelector('[data-course-discovery-open-filters]');
		const drawer = root.querySelector('.course-discovery__filters');
		const backdrop = root.querySelector('.course-discovery__backdrop');
		const closeButtons = drawer?.querySelectorAll('[data-course-discovery-close-filters]');
		const heading = drawer?.querySelector('.course-discovery__filters-header h3');

		if (
			!(openButton instanceof HTMLElement) ||
			!(drawer instanceof HTMLElement) ||
			!(backdrop instanceof HTMLElement) ||
			!(heading instanceof HTMLElement) ||
			!closeButtons
		) {
			return;
		}

		let drawerOpen = false;
		root.classList.add('course-discovery--enhanced');

		const focusableElements = () =>
			Array.from(
				drawer.querySelectorAll(
					'a[href], button:not([disabled]), input:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'
				)
			).filter((element) => element instanceof HTMLElement && !element.hidden);

		const setCloseButtonsHidden = (hidden) => {
			closeButtons.forEach((button) => {
				if (button instanceof HTMLElement) {
					button.hidden = hidden;
				}
			});
		};

		const closeDrawer = (returnFocus = true) => {
			drawerOpen = false;
			drawer.hidden = true;
			backdrop.hidden = true;
			root.classList.remove('course-discovery--filters-open');
			openButton.setAttribute('aria-expanded', 'false');
			drawer.removeAttribute('aria-modal');
			drawer.removeAttribute('role');
			setCloseButtonsHidden(true);

			if (returnFocus) {
				openButton.focus();
			}
		};

		const openDrawer = () => {
			if (!mobileQuery.matches) {
				return;
			}

			drawerOpen = true;
			drawer.hidden = false;
			backdrop.hidden = false;
			root.classList.add('course-discovery--filters-open');
			openButton.setAttribute('aria-expanded', 'true');
			drawer.setAttribute('aria-modal', 'true');
			drawer.setAttribute('role', 'dialog');
			setCloseButtonsHidden(false);
			heading.focus();
		};

		const synchronizeLayout = () => {
			if (mobileQuery.matches) {
				if (drawerOpen) {
					openDrawer();
				} else {
					closeDrawer(false);
				}

				return;
			}

			drawerOpen = false;
			drawer.hidden = false;
			backdrop.hidden = true;
			root.classList.remove('course-discovery--filters-open');
			openButton.setAttribute('aria-expanded', 'true');
			drawer.removeAttribute('aria-modal');
			drawer.removeAttribute('role');
			setCloseButtonsHidden(true);
		};

		openButton.addEventListener('click', (event) => {
			if (!mobileQuery.matches) {
				return;
			}

			event.preventDefault();
			openDrawer();
		});

		closeButtons.forEach((button) => {
			button.addEventListener('click', (event) => {
				event.preventDefault();
				closeDrawer();
			});
		});

		backdrop.addEventListener('click', () => closeDrawer());

		root.addEventListener('keydown', (event) => {
			if (!drawerOpen || !mobileQuery.matches) {
				return;
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				closeDrawer();
				return;
			}

			if (event.key !== 'Tab') {
				return;
			}

			const focusable = focusableElements();

			if (focusable.length === 0) {
				event.preventDefault();
				heading.focus();
				return;
			}

			const first = focusable[0];
			const last = focusable[focusable.length - 1];

			if (
				event.shiftKey &&
				(document.activeElement === first || document.activeElement === heading)
			) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		if (typeof mobileQuery.addEventListener === 'function') {
			mobileQuery.addEventListener('change', synchronizeLayout);
		} else {
			mobileQuery.addListener(synchronizeLayout);
		}

		synchronizeLayout();
	};

	const initializeAll = () => {
		document.querySelectorAll('[data-course-discovery]').forEach(initialize);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeAll);
	} else {
		initializeAll();
	}
})();
