/**
 * Homepage category carousels — drag with mouse to scroll horizontally.
 */
(function () {
    'use strict';

    const DRAG_CLICK_THRESHOLD = 8;

    document.querySelectorAll('[data-category-carousel]').forEach(function (viewport) {
        let isDragging = false;
        let startX = 0;
        let scrollStart = 0;
        let dragDistance = 0;

        function endDrag() {
            if (!isDragging) return;
            isDragging = false;
            viewport.classList.remove('is-dragging');
        }

        viewport.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            isDragging = true;
            dragDistance = 0;
            startX = e.pageX;
            scrollStart = viewport.scrollLeft;
            viewport.classList.add('is-dragging');
        });

        window.addEventListener('mouseup', endDrag);
        viewport.addEventListener('mouseleave', endDrag);

        viewport.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            e.preventDefault();
            const dx = e.pageX - startX;
            dragDistance = Math.max(dragDistance, Math.abs(dx));
            viewport.scrollLeft = scrollStart - dx;
        });

        viewport.addEventListener(
            'click',
            function (e) {
                if (dragDistance < DRAG_CLICK_THRESHOLD) return;
                const link = e.target.closest('a');
                if (link && viewport.contains(link)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            },
            true
        );

        viewport.addEventListener(
            'dragstart',
            function (e) {
                e.preventDefault();
            },
            true
        );
    });
})();
