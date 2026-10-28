{{--
    Keeps what is typed on a new purchase invoice in this browser, under the signed-in user, so a refresh or a closed tab
    loses nothing: when the page opens again, the copy is handed to the page to put back in the form.
    The images are left out, because an upload does not outlive the page.
    The copy is written only after the user touched the form and only while it differs from the form as it opened,
    and it is forgotten once the invoice is created or the user discards it. When the browser storage is full or blocked,
    the form keeps working, only without its copy.

    $storageKey: the browser storage key of the signed-in user's copy
--}}
<div
    x-data="{
        key: @js($storageKey),
        pristine: null,
        touched: false,
        timer: null,
        flush: null,

        init() {
            this.pristine = JSON.stringify(this.snapshot())

            let saved = localStorage.getItem(this.key)

            if (saved !== null) {
                try {
                    this.$wire.restoreDraft(JSON.parse(saved))
                } catch (error) {
                    localStorage.removeItem(this.key)
                }
            }

            ['input', 'change', 'pointerdown', 'keydown'].forEach((event) => {
                this.$wire.$el.addEventListener(event, () => this.touched = true)
            })

            this.$wire.$watch('data', () => this.queue())
            this.$wire.$on('purchase-invoice-draft-cleared', () => this.forget())

            this.flush = () => this.save()
            window.addEventListener('beforeunload', this.flush)
        },

        destroy() {
            window.removeEventListener('beforeunload', this.flush)
            this.save()
        },

        snapshot() {
            let data = JSON.parse(JSON.stringify(this.$wire.data ?? {}))
            delete data.images

            return data
        },

        queue() {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => this.save(), 400)
        },

        save() {
            clearTimeout(this.timer)

            if (! this.touched) {
                return
            }

            let data = JSON.stringify(this.snapshot())

            try {
                data === this.pristine ? localStorage.removeItem(this.key) : localStorage.setItem(this.key, data)
            } catch (error) {
                return
            }
        },

        forget() {
            clearTimeout(this.timer)
            this.touched = false
            localStorage.removeItem(this.key)
            setTimeout(() => this.pristine = JSON.stringify(this.snapshot()))
        },
    }"
    style="display: none"
></div>
