// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Autonomous authoring geometry and independent image/block gestures.
 * @module qtype_clozeonimage/composition_editor
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['qtype_clozeonimage/layout', 'qtype_clozeonimage/form', 'qtype_clozeonimage/composition_geometry',
    'qtype_clozeonimage/pixel_fields', 'core/notification', 'core/str'],
    function(Layout, Blocks, Geometry, Pixels, Notification, Str) {
        'use strict';
        const init = () => {
            const form = document.querySelector('form[data-qtype="clozeonimage"]');
            const composition = form?.querySelector('[data-composition]');
            if (!composition || composition.dataset.editorReady) {
                return;
            }
            composition.dataset.editorReady = '1';
            Pixels.init();
            const area = composition.closest('.qtype-clozeonimage-preview-area');
            const field = (name, index = null) => form.querySelector(`[name="${name}${index === null ? '' : `[${index}]`}"]`);
            const blocks = () => [...composition.querySelectorAll('.qtype-clozeonimage-preview-subquestion')];
            const images = () => [...composition.querySelectorAll('.qtype-clozeonimage-preview-scene-image')];
            const imageByKey = key => images().find(image => image.dataset.imageKey === key);
            const position = element => element.dataset.imageKey ? {
                x: Number(field('imageleft', element.dataset.imageIndex).value),
                y: Number(field('imagetop', element.dataset.imageIndex).value),
            } : Blocks.precisePosition(element);
            const imageGeometry = image => ({...position(image),
                width: Number(field('imagewidth', image.dataset.imageIndex).value),
                height: Number(field('imageheight', image.dataset.imageIndex).value)});
            let base = JSON.parse(composition.dataset.composition);
            let writing = false;
            let following = false;
            let initialised = Number(field('compositionwidth').value) > 0;
            field('compositionbackground').type = 'color';

            const selected = new Set();
            let cancelGesture = null;
            const selectedClass = 'qtype-clozeonimage-preview-selected';
            const grips = [...composition.querySelectorAll('.qtype-clozeonimage-composition-resize')];
            const reset = form.querySelector('[data-action=clozeonimage-reset-coordinates]');
            const limitError = () => Str.get_string('geometrylimit', 'qtype_clozeonimage').then(message =>
                Notification.alert('', message));

            const writeBase = (next, update = true) => {
                if (!Geometry.validRectangle(next)) {
                    limitError();
                    return;
                }
                base = next.locked ? next : {...next, ratio: next.width / next.height};
                const ratioChoice = field('compositionpreset');
                ratioChoice.value = ['4:3', '16:9', '16:10', '1:1'].find(value => {
                    const [w, h] = value.split(':').map(Number);
                    return Math.abs(w / h - base.ratio) < .0001;
                }) || 'custom';
                composition.dataset.composition = JSON.stringify(base);
                for (const [key, value] of Object.entries(base)) {
                    const input = field(`composition${key}`);
                    if (!input) {
                        continue;
                    }
                    if (key === 'locked') {
                        // QuickForm emits a hidden zero and a checkbox with the same name.
                        form.querySelector('[name="compositionlocked"][type=checkbox]').checked = !!value;
                    } else {
                        input.value = value;
                    }
                }
                grips.forEach(grip => {
                    const tl = grip.dataset.corner === 'top-left';
                    grip.style.left = `${base.x + (tl ? 0 : base.width)}px`;
                    grip.style.top = `${base.y + (tl ? 0 : base.height)}px`;
                });
                reset.disabled = base.x === 0 && base.y === 0;
                if (update) {
                    writing = true;
                    Layout.updateCompositionHeight(composition);
                    writing = false;
                }
            };
            // Grow from internal persistent rectangles only, never screen-space translations.
            composition.addEventListener('clozeonimage:bounds', event => {
                if (writing || !initialised) {
                    return;
                }
                const b = event.detail;
                if (b.minX < base.x - .01 || b.minY < base.y - .01 ||
                        b.maxX > base.x + base.width + .01 || b.maxY > base.y + base.height + .01) {
                    writeBase({...base, x: b.minX, y: b.minY, width: b.maxX - b.minX, height: b.maxY - b.minY}, false);
                }
            });
            const resizeBase = (width, height) => {
                const contents = Layout.measureContent(composition, true)?.bounds;
                const b = Geometry.extent({...base, width, height}, contents ? [{left: contents.minX,
                    top: contents.minY, right: contents.maxX, bottom: contents.maxY}] : []);
                writeBase({...base, x: b.minX, y: b.minY, width: b.maxX - b.minX, height: b.maxY - b.minY});
            };
            const initialise = () => {
                if (!initialised && area.getClientRects().length && area.clientWidth > 0) {
                    initialised = true;
                    const width = Math.max(40, area.clientWidth - 16);
                    writeBase({...base, x: 0, y: 0, width, height: width / base.ratio});
                    Layout.updateCompositionHeight(composition);
                }
            };
            const visibility = new ResizeObserver(initialise);
            visibility.observe(area);
            initialise();
            if (initialised) {
                writeBase(base);
            }
            const preset = field('compositionpreset');
            preset.value = ['4:3', '16:9', '16:10', '1:1'].find(value => {
                const [w, h] = value.split(':').map(Number);
                return Math.abs(w / h - base.ratio) < .0001;
            }) || 'custom';

            const attach = block => {
                if (following) {
                    return;
                }
                const i = block.dataset.rowIndex;
                const image = imageByKey(field('relativeimage', i)?.value);
                if (!image || imageGeometry(image).width <= 0 || imageGeometry(image).height <= 0) {
                    return;
                }
                const point = Geometry.relative(imageGeometry(image), position(block), block.getBoundingClientRect(),
                    Blocks.getAnchor(i));
                field('relativex', i).value = point.x;
                field('relativey', i).value = point.y;
            };
            const follow = image => {
                following = true;
                blocks().filter(block => field('relativeimage', block.dataset.rowIndex)?.value === image.dataset.imageKey)
                    .forEach(block => {
                        const i = block.dataset.rowIndex;
                        const point = Geometry.attached(imageGeometry(image),
                            {x: Number(field('relativex', i).value), y: Number(field('relativey', i).value)},
                            block.getBoundingClientRect(), Blocks.getAnchor(i));
                        Blocks.setPosition(block, point.x, point.y, false);
                    });
                following = false;
            };
            composition.addEventListener('clozeonimage:blockposition', event => attach(event.target));
            const writeImage = (image, value) => {
                const i = image.dataset.imageIndex;
                for (const [property, name] of [['x', 'left'], ['y', 'top'], ['width', 'width'], ['height', 'height']]) {
                    image.style[name] = `${value[property]}px`;
                    field(`image${name}`, i).value = value[property];
                }
                follow(image);
            };
            const batch = callback => {
                composition.dataset.gestureBatch = '1';
                try {
                    callback();
                } finally {
                    delete composition.dataset.gestureBatch;
                }
                Layout.updateCompositionHeight(composition);
            };
            const clear = () => {
                selected.forEach(element => element.classList.remove(selectedClass));
                selected.clear();
            };
            const bind = element => {
                if (element.dataset.gestureReady) {
                    return;
                }
                element.dataset.gestureReady = '1';
                const marker = document.createElement('span');
                marker.className = 'qtype-clozeonimage-preview-selection-mark';
                marker.textContent = '\u2713';
                marker.setAttribute('aria-hidden', 'true');
                element.append(marker);
                element.addEventListener('pointerdown', event => {
                    if (event.button !== 0 || cancelGesture) {
                        return;
                    }
                    const resize = !!event.target.closest('.qtype-clozeonimage-resize, .qtype-clozeonimage-image-resize');
                    event.preventDefault();
                    if (!resize && (event.ctrlKey || event.metaKey)) {
                        if (selected.has(element)) {
                            selected.delete(element);
                        } else {
                            selected.add(element);
                        }
                        element.classList.toggle(selectedClass, selected.has(element));
                        return;
                    }
                    if (!resize && !selected.has(element)) {
                        clear();
                    }
                    const moving = [...(!resize && selected.has(element) ? selected : [element])];
                    const snapshots = new Map(moving.map(item => [item, item.dataset.imageKey ? imageGeometry(item) :
                        {...position(item), width: item.getBoundingClientRect().width,
                            height: item.getBoundingClientRect().height}]));
                    const start = {x: event.clientX, y: event.clientY};
                    composition.dataset.frozenPlacement = '1';
                    // Pointer deltas are applied once to a fixed internal snapshot. Display offsets never feed back.
                    const klass = resize ? 'qtype-clozeonimage-preview-resizing' : 'qtype-clozeonimage-preview-dragging';
                    element.classList.add(klass);
                    document.documentElement.classList.add(resize ? 'qtype-clozeonimage-resizing' : 'qtype-clozeonimage-moving');
                    element.setPointerCapture(event.pointerId);
                    const move = e => batch(() => {
                        const dx = e.clientX - start.x;
                        const dy = e.clientY - start.y;
                        if (resize) {
                            const before = snapshots.get(element);
                            if (element.dataset.imageKey) {
                                const width = Math.max(1, before.width + dx);
                                writeImage(element, {...before, width, height: width * before.height / before.width});
                            } else {
                                Blocks.setBlockWidth(element, Math.max(40, before.width + dx), false, before);
                                attach(element);
                            }
                        } else {
                            moving.filter(item => item.dataset.imageKey).forEach(image => {
                                const before = snapshots.get(image);
                                writeImage(image, {...before, x: before.x + dx, y: before.y + dy});
                            });
                            moving.filter(item => !item.dataset.imageKey).forEach(block => {
                                const image = imageByKey(field('relativeimage', block.dataset.rowIndex)?.value);
                                if (image && moving.includes(image)) {
                                    return; // Its anchor has already followed the selected image exactly once.
                                }
                                const before = snapshots.get(block);
                                Blocks.setPosition(block, before.x + dx, before.y + dy, false);
                            });
                        }
                    });
                    const stop = e => {
                        if (e.type === 'pointerup') {
                            move(e);
                        }
                        cancelGesture = null;
                        delete composition.dataset.frozenPlacement;
                        Layout.updateCompositionHeight(composition);
                        element.classList.remove(klass);
                        document.documentElement.classList.remove('qtype-clozeonimage-resizing', 'qtype-clozeonimage-moving');
                        for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
                            element.removeEventListener(type, stop);
                        }
                        element.removeEventListener('pointermove', move);
                        if (element.hasPointerCapture(event.pointerId)) {
                            element.releasePointerCapture(event.pointerId);
                        }
                    };
                    cancelGesture = stop;
                    element.addEventListener('pointermove', move);
                    for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
                        element.addEventListener(type, stop);
                    }
                });
            };
            blocks().forEach(bind);
            images().forEach(bind);
            area.addEventListener('pointerdown', event => {
                if (!event.target.closest('.qtype-clozeonimage-preview-subquestion, .qtype-clozeonimage-preview-scene-image')) {
                    clear();
                }
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    cancelGesture?.(event);
                    clear();
                }
            });
            const contentBounds = () => blocks().length || images().length ?
                Layout.measureContent(composition, true)?.bounds : null;
            grips.forEach(grip => {
                const tl = grip.dataset.corner === 'top-left';
                grip.addEventListener('keydown', event => {
                    const directions = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};
                    if (directions[event.key]) {
                        event.preventDefault();
                        writeBase(Geometry.resizeCorner(base, ...directions[event.key], tl, contentBounds()));
                    }
                });
                grip.addEventListener('pointerdown', event => {
                    if (event.button !== 0 || cancelGesture) {
                        return;
                    }
                    event.preventDefault();
                    const original = {...base}, start = {x: event.clientX, y: event.clientY};
                    const contents = contentBounds();
                    composition.dataset.frozenPlacement = '1';
                    grip.setPointerCapture(event.pointerId);
                    document.documentElement.classList.add('qtype-clozeonimage-composition-resizing');
                    const move = e => writeBase(Geometry.resizeCorner(original, e.clientX - start.x,
                        e.clientY - start.y, tl, contents));
                    const stop = e => {
                        if (e.type === 'pointerup') {
                            move(e);
                        }
                        grip.removeEventListener('pointermove', move);
                        for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
                            grip.removeEventListener(type, stop);
                        }
                        cancelGesture = null;
                        delete composition.dataset.frozenPlacement;
                        Layout.updateCompositionHeight(composition);
                        document.documentElement.classList.remove('qtype-clozeonimage-composition-resizing');
                        if (grip.hasPointerCapture(event.pointerId)) {
                            grip.releasePointerCapture(event.pointerId);
                        }
                    };
                    cancelGesture = stop;
                    grip.addEventListener('pointermove', move);
                    for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
                        grip.addEventListener(type, stop);
                    }
                });
            });
            reset.addEventListener('click', async() => {
                const strings = await Str.get_strings([
                    {key: 'resetcoordinatestitle', component: 'qtype_clozeonimage'},
                    {key: 'resetcoordinatesconfirm', component: 'qtype_clozeonimage'},
                    {key: 'resetcoordinates', component: 'qtype_clozeonimage'},
                    {key: 'cancel', component: 'moodle'},
                ]);
                Notification.confirm(...strings, () => {
                    const entries = images().map(element => ({element,
                        point: {x: position(element).x - base.x, y: position(element).y - base.y}}));
                    form.querySelectorAll('input[data-pixel-value][name^="xleft["]').forEach(input => {
                        const i = input.name.match(/\[(\d+)]/)[1];
                        entries.push({element: blocks().find(block => block.dataset.rowIndex === i), index: i,
                            point: {x: Number(input.value) - base.x, y: Number(field('ytop', i).value) - base.y}});
                    });
                    if (entries.some(({point}) => ![point.x, point.y].every(value =>
                        Number.isFinite(value) && Math.abs(value) <= 4194303))) {
                        limitError();
                        return;
                    }
                    // One atomic translation, never follow associated blocks a second time.
                    following = true;
                    batch(() => {
                        entries.forEach(({element, point, index}) => {
                            if (element?.dataset.imageKey) {
                                const i = element.dataset.imageIndex;
                                element.style.left = `${point.x}px`;
                                element.style.top = `${point.y}px`;
                                field('imageleft', i).value = point.x;
                                field('imagetop', i).value = point.y;
                            } else if (element) {
                                Blocks.setPosition(element, point.x, point.y, false);
                            } else {
                                field('xleft', index).value = point.x;
                                field('ytop', index).value = point.y;
                            }
                        });
                        writeBase({...base, x: 0, y: 0}, false);
                    });
                    following = false;
                });
            });
            const inactive = () => {
                const enabled = form.querySelector('[name=showcomposition][type=checkbox]').checked;
                form.querySelector('.qtype-clozeonimage-preview-message').hidden = enabled;
                composition.classList.toggle('qtype-clozeonimage-inactive', !enabled);
            };
            inactive();
            form.addEventListener('change', event => {
                const {name, value} = event.target;
                if (/^(blocklayer|imagelayer)\[/.test(name) && !event.target.checkValidity()) {
                    return;
                }
                if (name === 'showcomposition') {
                    inactive();
                } else if (name === 'compositionlocked') {
                    writeBase({...base, locked: event.target.checked ? 1 : 0, ratio: base.width / base.height});
                } else if (name === 'compositionpreset' && value !== 'custom') {
                    const [w, h] = value.split(':').map(Number);
                    base.ratio = w / h;
                    resizeBase(base.width, base.width / base.ratio);
                } else if (name === 'compositionwidth' || name === 'compositionheight') {
                    if (Number(value) > 0 && Number(value) <= 4194303) {
                        const width = name === 'compositionwidth' ? Number(value) :
                            base.locked ? Number(value) * base.ratio : base.width;
                        const height = name === 'compositionheight' ? Number(value) :
                            base.locked ? Number(value) / base.ratio : base.height;
                        if (!base.locked) {
                            base.ratio = width / height;
                            preset.value = 'custom';
                        }
                        resizeBase(width, height);
                    }
                } else if (name === 'compositionbackground' && /^#[0-9a-f]{6}$/i.test(value)) {
                    writeBase({...base, background: value});
                }
                const blockmatch = name?.match(/^(relativeimage|blocklayer|anchor)\[(\d+)]$/);
                if (blockmatch) {
                    const block = blocks().find(b => b.dataset.rowIndex === blockmatch[2]);
                    if (block) {
                        attach(block);
                        block.dataset.layer = field('blocklayer', blockmatch[2]).value;
                        Layout.rankLayers(composition);
                    }
                }
                const imagematch = name?.match(/^(imageleft|imagetop|imagewidth|imagelayer|imagename|imageremove)\[(\d+)]$/);
                if (imagematch) {
                    const i = imagematch[2], image = images().find(img => img.dataset.imageIndex === i);
                    if (image && imagematch[1] === 'imageremove' && event.target.checked) {
                        blocks().filter(b => field('relativeimage', b.dataset.rowIndex).value === image.dataset.imageKey)
                            .forEach(b => {
                                field('relativeimage', b.dataset.rowIndex).value = '';
                            });
                        blocks().forEach(b => [...field('relativeimage', b.dataset.rowIndex).options]
                            .filter(option => option.value === image.dataset.imageKey).forEach(option => option.remove()));
                        selected.delete(image);
                        image.remove();
                        Layout.updateCompositionHeight(composition);
                    } else if (image) {
                        const old = imageGeometry(image), width = Number(field('imagewidth', i).value);
                        const intrinsic = image.querySelector('img');
                        const ratio = intrinsic.naturalHeight / intrinsic.naturalWidth || old.height / old.width;
                        if (width > 0) {
                            batch(() => writeImage(image, {x: Number(field('imageleft', i).value),
                                y: Number(field('imagetop', i).value), width, height: width * ratio}));
                        }
                        image.dataset.layer = field('imagelayer', i).value;
                        Layout.rankLayers(composition);
                        if (imagematch[1] === 'imagename') {
                            image.querySelector('img').alt = value;
                            blocks().forEach(b => [...field('relativeimage', b.dataset.rowIndex).options]
                                .filter(option => option.value === image.dataset.imageKey)
                                .forEach(option => {
                                    option.textContent = value;
                                }));
                        }
                    }
                    if (imagematch[1] === 'imageremove' && !event.target.checked) {
                        refreshImage(i);
                    }
                }
            });
            const refreshImage = i => {
                const input = field('sceneimage', i);
                const link = input.closest('.fitem')?.querySelector('.filepicker-filename a');
                if (!link || form.querySelector(`[name="imageremove[${i}]"][type=checkbox]`)?.checked) {
                    return;
                }
                const key = field('imagekey', i).value;
                const pending = new Image();
                pending.onload = () => {
                    let image = imageByKey(key);
                    if (!image) {
                        image = document.createElement('div');
                        image.className = 'qtype-clozeonimage-preview-scene-image';
                        image.dataset.imageKey = key;
                        image.dataset.imageIndex = i;
                        image.innerHTML = '<img alt=""><span class="qtype-clozeonimage-image-resize" aria-hidden="true"></span>';
                        composition.append(image);
                        bind(image);
                    }
                    image.querySelector('img').src = pending.src;
                    image.querySelector('img').alt = field('imagename', i).value;
                    const width = Number(field('imagewidth', i).value) ||
                        Math.min(pending.naturalWidth, base.width, base.height * pending.naturalWidth / pending.naturalHeight);
                    batch(() => writeImage(image, {x: Number(field('imageleft', i).value),
                        y: Number(field('imagetop', i).value), width,
                        height: width * pending.naturalHeight / pending.naturalWidth}));
                    image.dataset.layer = field('imagelayer', i).value;
                        Layout.rankLayers(composition);
                    blocks().forEach(block => {
                        const select = field('relativeimage', block.dataset.rowIndex);
                        const option = [...select.options].find(o => o.value === key);
                        if (option) {
                            option.textContent = field('imagename', i).value;
                        } else {
                            select.add(new Option(field('imagename', i).value, key));
                        }
                    });
                };
                pending.src = link.href;
            };
            form.addEventListener('clozeonimage:imagefile', event => {
                const index = event.target.name.match(/\[(\d+)]/)?.[1];
                if (index !== undefined) {
                    refreshImage(index);
                }
            });
            // Standard filepicker fallback can initialise asynchronously without our dropzone callback.
            form.querySelectorAll('input.filepickerhidden[name^="sceneimage["]').forEach(input => {
                const i = input.name.match(/\[(\d+)]/)[1];
                let previous = '';
                const watch = () => {
                    const url = input.closest('.fitem')?.querySelector('.filepicker-filename a')?.href || '';
                    if (url && url !== previous) {
                        previous = url;
                        refreshImage(i);
                    }
                };
                new MutationObserver(watch).observe(input.closest('.fitem'), {childList: true, subtree: true});
                watch();
            });
            blocks().forEach(block => {
                block.dataset.layer = field('blocklayer', block.dataset.rowIndex)?.value || '100';
            });
            Layout.rankLayers(composition);
        };
        return {init};
    });
