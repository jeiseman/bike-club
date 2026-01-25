class CustomPrefixEventsEditor {
	constructor() {
		this.eventType = null;

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', this.DOMContentLoadedListener.bind(this));
		} else {
			this.DOMContentLoadedListener();
		}
	}

	DOMContentLoadedListener(event) {
		this.init();
	}

	eventTypeChangeListener(event) {
		this.maybeToggleRecurrencesBox();
	}

	init() {
		this.eventType = document.querySelector('.event_type');
		this.eventType.addEventListener('change', this.eventTypeChangeListener.bind(this));

		this.maybeToggleRecurrencesBox();
	}

	/**
	 * As of EM 7.0.5 the Recurrences Meta Box is hidden because the new /includes/js/src/parts/event-editor.js isn't compatible with the Block Editor
	 */
	maybeToggleRecurrencesBox() {
		let isRecurring = false;

		// Specifically targetting the Block Editor ".metabox-location-normal" (under the content) since EM omit this
		let blockEditorMetaBoxForm = document.querySelector('.metabox-location-normal');

		if (blockEditorMetaBoxForm) {
			isRecurring = this.eventType.type === 'checkbox' ? this.eventType.checked : this.eventType.value !== 'single';
			blockEditorMetaBoxForm.classList.toggle( 'em-is-recurring', isRecurring );

			if (this.eventType.type === 'checkbox') {
				blockEditorMetaBoxForm.classList.toggle('em-type-recurring', eventType.checked );
			}

			blockEditorMetaBoxForm.classList.remove( ...[ ...blockEditorMetaBoxForm.classList ].filter(className => className.startsWith( 'em-type-' )));
			blockEditorMetaBoxForm.classList.add('em-type-' + eventType.value);
		}
	}
}

new CustomPrefixEventsEditor();
