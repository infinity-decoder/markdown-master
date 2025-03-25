document.addEventListener("DOMContentLoaded", function () {
    // Load Prism.js dynamically
    function loadPrism() {
        if (!window.Prism) {
            let prismScript = document.createElement("script");
            prismScript.src = markdownMaster.prismUrl;
            prismScript.onload = () => Prism.highlightAll();
            document.head.appendChild(prismScript);
        } else {
            Prism.highlightAll();
        }
    }

    // Process all Markdown content and apply syntax highlighting
    function processMarkdownBlocks() {
        document.querySelectorAll(".markdown-content").forEach(block => {
            let markdownText = block.dataset.content;
            if (markdownText) {
                let markdownItParser = new markdownit({ html: true, linkify: true, typographer: true });
                block.innerHTML = markdownItParser.render(markdownText);
            }
        });

        // Apply Prism.js highlighting after rendering Markdown
        loadPrism();
    }

    // Wait for the page to load before processing Markdown
    processMarkdownBlocks();
});
