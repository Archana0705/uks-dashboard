if (window.jQuery) {
    $.ajaxSetup({
        headers: {
            "X-App-Key": "@Sfdb!",
            "X-App-Name": "sfdbsurvey"
        }
    });
} else {
    console.error("jQuery not loaded before global.js");
}
