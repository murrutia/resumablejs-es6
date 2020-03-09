import Resumable from './resumable-es6.js'
import { htmlToElement } from './utils.js'


export default class ResumableWidget
{
    constructor (elt, options)
    {
        this.elt = elt
        this.options = {
            // the unique id will be used as a HTML id, so it can't begin with a digit
            generateUniqueIdentifier: file => {
                const relativePath = file.webkitRelativePath || file.fileName || file.name
                return `f-${file.size}-${relativePath.replace(/[^0-9a-z_-]/img, '')}`
            },
            dropEltSelector: '.drop',
            dropEltActiveClass: 'dragover',
            browseEltSelector: '.browse',
            listEltClass: 'list',
            // all the other resumablejs options can be used as is
            ...options
        }
        this.resumable = new Resumable(this.options)
        this.initDrop()
        this.initBrowse()
        this.initList()
        this.initMainProgress()
        this.attachEvents()
    }

    attachEvents()
    {
        this.resumable.on('fileAdded', file => {
            this._fileRow(file)
            this.resumable.upload()
        })
        this.resumable.on('uploadStart', () => {
            this.elt.classList.add('active')
            this.elt.classList.remove('is-paused')
        })
        this.resumable.on('pause', () => this.elt.classList.add('is-paused'))
        this.resumable.on('complete', () => this.elt.classList.remove('active'))
        this.resumable.on('fileSuccess', file => {
            this.list_elt.querySelector(`#${file.uniqueIdentifier}`).classList.add('completed')
            this.list_elt.querySelector(`#${file.uniqueIdentifier} .status`).innerHTML = '(completed)'
        })
        this.resumable.on('fileError', (file, message) => {
            // Reflect that the file upload has resulted in error
            this.elt.classList.remove('active')
            this.elt.querySelector(`#${file.uniqueIdentifier} .status`).innerHTML = `(file could not be uploaded: ${message})'`
        })
        this.resumable.on('fileProgress', file => {
            const file_progress = Math.floor(file.progress() * 100)

            this.elt.querySelector(`#${file.uniqueIdentifier} .status`).innerHTML = file_progress + '%'
            this.elt.querySelector(`#${file.uniqueIdentifier} progress`).value = file_progress

            const global_progress = Math.floor(this.resumable.progress() * 100)
            this.elt.querySelector(`.main.progress progress`).value = global_progress
        })
        this.resumable.on('cancel', e => {
            this.elt.classList.remove('active')
            this.elt.querySelector('.file:not(.completed)').classList.add('canceled')
            this.elt.querySelector('.file:not(.completed) .status').innerHTML = 'canceled'
        })
    }

    _fileRow(file)
    {
        // If a row already exists, it means it was previously canceled
        let row = this.list_elt.querySelector('#'+file.uniqueIdentifier)
        if (row) {
            row.classList.remove('canceled')
            return row
        }

        const html = `
<li class="file" id="${file.uniqueIdentifier}">
    <p>
        Uploading ${file.fileName}
        <span class="status"></span>
    </p>
    <div class="progress">
        <progress max="100"></progress>
        <div class="controls">
            <span class="action cancel">cancel</span>
        </div>
    </div>
</li>
`
        row = htmlToElement(html)
        this.list_elt.append(row)
        row.querySelector('.action.cancel').addEventListener('click', e => {
            this.resumable.removeFile(file)
            row.classList.add('canceled')
            // There can be a fileSuccess event triggered after the cancel that will rewrite the status,
            // so we wait a bit before writing the 'canceled' status
            setTimeout(() => {
                row.querySelector('.status').innerHTML = 'canceled'
            }, 100);
        })
        return row

    }

    supported() {
        return this.resumable.support
    }

    initDrop()
    {
        const drop_area = this.elt.querySelector(this.options.dropEltSelector)

        // not mandatory, so we leave if there is no drop area
        if (! drop_area) return

        drop_area.addEventListener('dragenter', e => drop_area.classList.add(this.options.dropEltActiveClass))
        drop_area.addEventListener('dragleave', e => drop_area.classList.remove(this.options.dropEltActiveClass))
        drop_area.addEventListener('drop', e => drop_area.classList.remove(this.options.dropEltActiveClass))

        this.resumable.assignDrop(drop_area)
    }

    initBrowse()
    {
        const browse_elt = this.elt.querySelector(this.options.browseEltSelector)
        // not mandatory, so we leave if there is no drop area
        if (! browse_elt) return

        this.resumable.assignBrowse(browse_elt)
    }

    initList()
    {
        let list_elt = this.elt.querySelector('.'+this.options.listEltClass)

        if (! list_elt) {
            list_elt = htmlToElement( `<ul class="${this.options.listEltClass}"></ul> `)
            this.elt.append(list_elt)
        }
        this.list_elt = list_elt
    }

    initMainProgress()
    {
        let main_progress = this.elt.querySelector('.main.progress')
        if (! main_progress) {
            main_progress = htmlToElement(`
<div class="main progress">
    <progress ma="100"></progress>
    <div class="controls">
        <span class="action resume" title="Resume upload">  resume </span>
        <span class="action pause" title="Pause upload">    pause </span>
        <span class="action cancel" title="Cancel upload">  cancel </span>
    </div>
</div>`)
            this.elt.prepend(main_progress)
        }

        main_progress.querySelectorAll('.action.resume').forEach(elt =>
            elt.addEventListener('click', e => this.resume())
        )
        main_progress.querySelectorAll('.action.pause').forEach(elt =>
            elt.addEventListener('click', e => this.pause())
        )
        main_progress.querySelectorAll('.action.cancel').forEach(elt =>
            elt.addEventListener('click', e => this.cancel())
        )
    }

    resume()
    {
        this.resumable.upload()
    }
    pause()
    {
        this.resumable.pause()
    }
    cancel()
    {
        this.resumable.cancel()
    }
}
