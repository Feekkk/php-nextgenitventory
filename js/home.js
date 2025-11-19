document.addEventListener("DOMContentLoaded", function() {
    const text = "Next-Gen IT Asset\nManagement";
    const target = document.getElementById("typed-title");
    let i = 0;
    function type() {
        let html = '';
        let j = 0;
        while (j < i) {
            // Support line break
            if (text[j] === "\n") {
                html += "<br>";
            } else {
                html += text[j];
            }
            j++;
        }
        target.innerHTML = html;
        if (i < text.length) {
            i++;
            setTimeout(type, 40);
        }
    }
    type();
});