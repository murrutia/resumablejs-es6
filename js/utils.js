
export function htmlToElement(html) {
    const template = document.createElement('template');
    html = html.trim(); // Never return a text node of whitespace as the result
    if (html[0] != '<') html = `<span>${html}</span>`
    template.innerHTML = html;
    return template.content.firstChild;
}
