// The per-user layer of a SplitButton: long press / E to enter edit mode,
// reorder (drag or Ctrl+arrows), hide, pin to the button, reset, and the
// "last used" beacon. Loaded on demand, only for buttons with user state —
// a static split button is plain Filament markup with no script at all.
export default function splitButton() {
    return {
        editing: false,

        dirty: false,

        sections: [],

        pinned: null,

        declared: null,

        rememberLast: false,

        canRememberLast: false,

        suppressClick: false,

        pressTimer: null,

        pressOrigin: null,

        rootEl: null,

        panelEl: null,

        dropdownEl: null,

        triggerEl: null,

        closeObserver: null,

        editKeyListener: null,

        // This component, under a name nothing else uses. The trigger and
        // the panel live inside Filament's dropdown scope, where its
        // members (isOpen, close, …) shadow ours and `$root` is the
        // dropdown — so the template and the handlers below always go
        // through `sb`.
        sb: null,

        // Bound (x-bind) to the trigger and to the panel.
        surface: {
            ['x-on:pointerdown'](event) {
                this.sb.pressStart(event)
            },
            ['x-on:pointermove'](event) {
                this.sb.pressMove(event)
            },
            ['x-on:pointerup']() {
                this.sb.pressEnd()
            },
            ['x-on:pointercancel']() {
                this.sb.pressEnd()
            },
            ['x-on:contextmenu'](event) {
                if (this.sb.pressTimer || this.sb.suppressClick) {
                    event.preventDefault()
                }
            },
            ['x-on:click.capture'](event) {
                this.sb.onClickCapture(event)
            },
            ['x-on:keydown'](event) {
                this.sb.onKeydown(event)
            },
        },

        init() {
            this.sb = this
            this.rootEl = this.$el
            this.dropdownEl = this.rootEl.querySelector('.fi-dropdown')
            this.triggerEl = this.dropdownEl?.querySelector(
                ':scope > .fi-dropdown-trigger',
            )
            this.panelEl = this.dropdownEl?.querySelector(
                ':scope > .fi-dropdown-panel',
            )

            this.editKeyListener = (event) => this.onEditKeydown(event)
        },

        destroy() {
            this.pressEnd()
            this.closeObserver?.disconnect()
            window.removeEventListener('keydown', this.editKeyListener, true)
        },

        config() {
            try {
                return JSON.parse(this.rootEl?.dataset.splitbutton ?? '{}')
            } catch {
                return {}
            }
        },

        isOpen() {
            return (
                !!this.panelEl &&
                (this.panelEl._x_isShown === true ||
                    this.panelEl.style.display === 'block')
            )
        },

        openPanel() {
            if (this.isOpen() || !this.triggerEl) {
                return
            }

            // Filament toggles on mousedown, in every version — go the
            // same way a user would.
            const wasSuppressed = this.suppressClick
            this.suppressClick = false
            this.triggerEl.dispatchEvent(
                new MouseEvent('mousedown', { bubbles: true, button: 0 }),
            )
            this.suppressClick = wasSuppressed
        },

        closePanel() {
            if (!this.isOpen()) {
                return
            }

            window.Alpine.$data(this.dropdownEl)?.close()
        },

        // Long press ---------------------------------------------------

        pressStart(event) {
            if (this.editing || (event.button ?? 0) !== 0) {
                return
            }

            if (
                !(event.target instanceof Element) ||
                !event.target.closest(
                    '.fi-dropdown-trigger, .fi-dropdown-list-item',
                )
            ) {
                return
            }

            const config = this.config()

            if (!config.editable) {
                return
            }

            this.pressEnd()
            this.pressOrigin = { x: event.clientX, y: event.clientY }
            this.pressTimer = setTimeout(() => {
                this.pressTimer = null
                this.suppressClick = true
                this.startEditing()
            }, config.longPress ?? 500)
        },

        pressMove(event) {
            if (!this.pressTimer || !this.pressOrigin) {
                return
            }

            const distance = Math.hypot(
                event.clientX - this.pressOrigin.x,
                event.clientY - this.pressOrigin.y,
            )

            if (distance > 10) {
                this.pressEnd()
            }
        },

        pressEnd() {
            if (this.pressTimer) {
                clearTimeout(this.pressTimer)
                this.pressTimer = null
            }

            // The click that ends a long press arrives right after the
            // pointerup; anything later is a new, real click.
            if (this.suppressClick) {
                setTimeout(() => (this.suppressClick = false), 80)
            }
        },

        onClickCapture(event) {
            if (this.suppressClick) {
                this.suppressClick = false
                event.preventDefault()
                event.stopImmediatePropagation()

                return
            }

            if (this.editing || !(event.target instanceof Element)) {
                return
            }

            const option = event.target.closest('[data-splitbutton-option]')

            if (option && this.panelEl?.contains(option)) {
                this.trackUse(option.dataset.splitbuttonOption)
            }
        },

        onKeydown(event) {
            if (
                this.editing ||
                event.ctrlKey ||
                event.metaKey ||
                event.altKey ||
                (event.key !== 'e' && event.key !== 'E')
            ) {
                return
            }

            if (!this.config().editable) {
                return
            }

            event.preventDefault()
            event.stopPropagation()
            this.startEditing()
        },

        // Edit mode ----------------------------------------------------

        startEditing() {
            const config = this.config()

            if (!config.editable || this.editing) {
                return
            }

            this.sections = (config.sections ?? []).map((section) =>
                section.map((item) => ({ ...item })),
            )
            this.pinned = config.pinned ?? null
            this.declared = config.declared ?? null
            this.rememberLast = !!config.rememberLast
            this.canRememberLast = !!config.canRememberLast
            this.dirty = false
            this.editing = true

            this.openPanel()

            window.addEventListener('keydown', this.editKeyListener, true)
            this.watchClose()

            this.focusWhenReady()
        },

        stopEditing() {
            if (!this.editing) {
                return
            }

            this.editing = false
            this.closeObserver?.disconnect()
            this.closeObserver = null
            window.removeEventListener('keydown', this.editKeyListener, true)

            if (this.dirty) {
                this.dirty = false
                this.$wire?.$refresh()
            }
        },

        finish() {
            this.closePanel()
            this.stopEditing()
        },

        // Leaving by any road (click outside, Escape, Tab) ends edit mode.
        watchClose() {
            if (!this.panelEl) {
                return
            }

            // The panel is positioned (style changes) while still hidden,
            // just before it shows — only a panel that was open can close.
            let wasOpen = this.isOpen()

            this.closeObserver?.disconnect()
            this.closeObserver = new MutationObserver(() => {
                if (this.panelEl.style.display !== 'none') {
                    wasOpen = true
                } else if (wasOpen && this.editing) {
                    this.stopEditing()
                }
            })
            this.closeObserver.observe(this.panelEl, {
                attributes: true,
                attributeFilter: ['style'],
            })
        },

        face() {
            if (this.pinned) {
                return this.pinned
            }

            const visible = this.sections
                .flat()
                .filter((item) => item.pinnable && !item.hidden)

            return (
                visible.find((item) => item.name === this.declared)?.name ??
                visible[0]?.name ??
                null
            )
        },

        isFace(name) {
            return !this.config().overflow && this.face() === name
        },

        isPinned(name) {
            return this.pinned === name
        },

        find(name) {
            for (const section of this.sections) {
                const item = section.find((item) => item.name === name)

                if (item) {
                    return item
                }
            }

            return null
        },

        togglePin(name) {
            const item = this.find(name)

            if (!item?.pinnable) {
                return
            }

            if (this.pinned === name) {
                this.pinned = null
            } else {
                this.pinned = name
                item.hidden = false
            }

            this.save()
        },

        toggleHidden(name) {
            const item = this.find(name)

            if (!item) {
                return
            }

            if (!item.hidden) {
                // Something has to stay on the button.
                const others = this.sections
                    .flat()
                    .filter(
                        (other) =>
                            other.pinnable &&
                            !other.hidden &&
                            other.name !== name,
                    )

                if (item.pinnable && others.length === 0) {
                    return
                }

                if (this.pinned === name) {
                    this.pinned = null
                }
            }

            item.hidden = !item.hidden
            this.save()
        },

        toggleRememberLast() {
            this.rememberLast = !this.rememberLast
            this.save()
        },

        reorder(sectionIndex, names) {
            const section = this.sections[sectionIndex]

            if (!section) {
                return
            }

            const order = new Map(names.map((name, index) => [name, index]))

            this.sections[sectionIndex] = [...section].sort(
                (a, b) => (order.get(a.name) ?? 0) - (order.get(b.name) ?? 0),
            )
            this.save()
        },

        move(name, delta) {
            const sectionIndex = this.sections.findIndex((section) =>
                section.some((item) => item.name === name),
            )

            if (sectionIndex < 0) {
                return
            }

            const section = [...this.sections[sectionIndex]]
            const from = section.findIndex((item) => item.name === name)
            const to = from + delta

            if (to < 0 || to >= section.length) {
                return
            }

            section.splice(to, 0, section.splice(from, 1)[0])
            this.sections[sectionIndex] = section
            this.save()

            this.$nextTick(() => this.focusName(name))
        },

        // Persistence --------------------------------------------------

        token() {
            return (
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ??
                this.config().token ??
                ''
            )
        },

        async post(url, body) {
            if (!url) {
                return false
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.token(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                })

                return response.ok
            } catch {
                return false
            }
        },

        save() {
            const config = this.config()
            const items = this.sections.flat()

            this.dirty = true

            return this.post(config.urls?.layout, {
                key: config.key,
                order: items.map((item) => item.name),
                hidden: items
                    .filter((item) => item.hidden)
                    .map((item) => item.name),
                default: this.pinned,
                remember_last: this.canRememberLast ? this.rememberLast : null,
            })
        },

        async reset() {
            const config = this.config()

            await this.post(config.urls?.layout, {
                key: config.key,
                reset: true,
            })

            this.dirty = true
            this.finish()
        },

        trackUse(name) {
            const config = this.config()

            if (!config.rememberLast || !config.urls?.used || !name) {
                return
            }

            const data = new FormData()
            data.append('_token', this.token())
            data.append('key', config.key)
            data.append('name', name)

            // A beacon outlives the navigation a URL entry starts.
            if (!navigator.sendBeacon?.(config.urls.used, data)) {
                this.post(config.urls.used, { key: config.key, name })
            }
        },

        // Keyboard in edit mode ----------------------------------------
        //
        // Registered on window in the capture phase so it runs before any
        // global shortcut layer (filament-mouseless listens on document).

        focusables() {
            return Array.from(
                this.panelEl?.querySelectorAll(
                    '.fi-splitbutton-edit-row, .fi-splitbutton-edit-footer .fi-dropdown-list-item',
                ) ?? [],
            )
        },

        // The rows render and the panel fades in a moment after edit mode
        // starts; keep trying until a row actually takes the focus.
        focusWhenReady(attempt = 0) {
            if (!this.editing || attempt > 12) {
                return
            }

            const first = this.focusables()[0]

            first?.focus()

            if (!first || document.activeElement !== first) {
                setTimeout(() => this.focusWhenReady(attempt + 1), 40)
            }
        },

        focusRow(index) {
            const items = this.focusables()

            items[Math.max(0, Math.min(index, items.length - 1))]?.focus()
        },

        focusName(name) {
            this.focusables()
                .find((element) => element.dataset.name === name)
                ?.focus()
        },

        onEditKeydown(event) {
            if (!this.editing || !this.panelEl) {
                return
            }

            const active = document.activeElement
            const inside = this.panelEl.contains(active)
            const onTrigger = this.triggerEl?.contains(active)

            if (!inside && !onTrigger) {
                return
            }

            const row = inside
                ? active.closest('.fi-splitbutton-edit-row')
                : null
            const name = row?.dataset.name
            const mod = event.ctrlKey || event.metaKey
            const items = this.focusables()
            const index = items.indexOf(
                inside
                    ? (active.closest(
                          '.fi-splitbutton-edit-row, .fi-dropdown-list-item',
                      ) ?? active)
                    : null,
            )

            let handled = true

            switch (event.key) {
                case 'ArrowDown':
                    mod && name
                        ? this.move(name, 1)
                        : this.focusRow(index < 0 ? 0 : (index + 1) % items.length)
                    break
                case 'ArrowUp':
                    mod && name
                        ? this.move(name, -1)
                        : this.focusRow(
                              index <= 0 ? items.length - 1 : index - 1,
                          )
                    break
                case 'Home':
                    this.focusRow(0)
                    break
                case 'End':
                    this.focusRow(items.length - 1)
                    break
                case 'h':
                case 'H':
                case ' ':
                    name && !mod && !event.altKey
                        ? this.toggleHidden(name)
                        : (handled = false)
                    break
                case 'd':
                case 'D':
                case 'Enter':
                    name && !mod && !event.altKey
                        ? this.togglePin(name)
                        : (handled = false)
                    break
                case 'Escape':
                    this.finish()
                    this.triggerEl?.querySelector('button, a')?.focus()
                    break
                default:
                    handled = false
            }

            if (handled) {
                event.preventDefault()
                event.stopImmediatePropagation()
            }
        },
    }
}
