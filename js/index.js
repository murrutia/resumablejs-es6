import ResumableWidget from './resumable-widget.js'

const rw = new ResumableWidget(document.querySelector('.resumable'), { target: '/upload.php' })

// Resumable.js isn't supported, fall back on a different method
if (!rw.supported()) {
    document.querySelector('.resumable .not-supported').style.display = 'inherit'
}
