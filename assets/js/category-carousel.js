/**
 * Homepage category carousels — drag to scroll; clicks on links still work.
 */
(function () {
    'use strict';

    const DRAG_THRESHOLD = 8;

    document.querySelectorAll('[data-category-carousel]').forEach(function (viewport) {
        let pointerDown = false;
        let isPanning = false;
        let startX = 0;
        let scrollStart = 0;
        let dragDistance = 0;

        function resetPointer() {
            pointerDown = false;
            isPanning = false;
            dragDistance = 0;
            viewport.classList.remove('is-panning');
        }

        function onPointerDown(clientX) {
            pointerDown = true;
            isPanning = false;
            dragDistance = 0;
            startX = clientX;
            scrollStart = viewport.scrollLeft;
        }

        function onPointerMove(clientX, preventDefault) {
            if (!pointerDown) return;

            var dx = clientX - startX;
            dragDistance = Math.max(dragDistance, Math.abs(dx));

            if (!isPanning && dragDistance >= DRAG_THRESHOLD) {
                isPanning = true;
                viewport.classList.add('is-panning');
            }

            if (!isPanning) return;

            if (preventDefault) {
                preventDefault();
            }
            viewport.scrollLeft = scrollStart - dx;
        }

        viewport.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            onPointerDown(e.pageX);
        });

        window.addEventListener('mouseup', resetPointer);
        viewport.addEventListener('mouseleave', function () {
            if (!isPanning) {
                pointerDown = false;
            }
        });

        viewport.addEventListener('mousemove', function (e) {
            if (!pointerDown) return;
            onPointerMove(e.pageX, function () {
                e.preventDefault();
            });
        });

        viewport.addEventListener(
            'click',
            function (e) {
                if (!isPanning && dragDistance < DRAG_THRESHOLD) return;
                var link = e.target.closest('a, button');
                if (link && viewport.contains(link)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            },
            true
        );

        viewport.addEventListener('touchstart', function (e) {
            if (!e.touches.length) return;
            onPointerDown(e.touches[0].clientX);
        }, { passive: true });

        viewport.addEventListener('touchmove', function (e) {
            if (!pointerDown || !e.touches.length) return;
            onPointerMove(e.touches[0].clientX, function () {
                if (isPanning) {
                    e.preventDefault();
                }
            });
        }, { passive: false });

        viewport.addEventListener('touchend', resetPointer);
        viewport.addEventListener('touchcancel', resetPointer);

        viewport.addEventListener('dragstart', function (e) {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
            }
        });
    });
})();
