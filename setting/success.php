<h1>Data Import Log!</h1>
<style>
    iframe.result {
        width: 80%;
        height: 400px;
        overflow-x: hidden;

    }
</style>

<iframe name="outputData" id="outputData" class="result" src="<?php echo plugin_dir_url(__DIR__); ?>reports/reports.html"></iframe>
<script>
    const iframeId = document.getElementById('outputData');
    let loadIframe = window.setInterval("reloadIFrame();", 5000);
    function reloadIFrame() {
        iframeId.contentWindow.location.reload()
        iframeId.contentWindow.scrollTo( 0, 999999999);
        let stopInterval = iframeId.contentWindow.document.getElementById('stop_interval');
        if(stopInterval.classList.contains('stop_interval')){
            clearInterval(loadIframe);
        }
    }



</script>