jQuery(document).ready(function ($) {
    $('.cotex-copy-btn').on('click', function () {
        var btn = $(this);
        var targetId = btn.data('clipboard-target');
        var codeText = $(targetId).text();

        navigator.clipboard.writeText(codeText).then(function () {
            var originalText = btn.text();
            btn.text('Copied!');
            setTimeout(function () {
                btn.text(originalText);
            }, 2000);
        }).catch(function (err) {
            console.error('Failed to copy: ', err);
        });
    });
});
