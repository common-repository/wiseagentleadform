function wa_sso(url) {
    var sso = window.open(url, 'sso', 'left=100,top=100,width=500,height=600');
    var timer = setInterval(function() {   
        if(sso.closed) {  
            clearInterval(timer);  
            console.log("sso closed");
            window.location.reload();
        }  
    }, 1000)
}

function copyToClipboard(element) {
    //get text from data-clipboard-text attribute
    var text = element.getAttribute("data-clipboard-text");

    if(window.clipboardData && window.clipboardData.setData) {
        window.clipboardData.setData(text);
        showWPAdminAlert();
    } else {
        if(navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
            showWPAdminAlert();
        } else {
                    // text area method
            let textArea = document.createElement("textarea");
            textArea.value = text;
            // make the textarea out of viewport
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            return new Promise((res, rej) => {
                // here the magic happens
                document.execCommand('copy') ? res() : rej();
                textArea.remove();
                showWPAdminAlert();
            });
        }
    }
}

function showWPAdminAlert() {
    var alert = document.getElementById("wp-admin-alert");
    alert.style.display = "block";
    setTimeout(function() {
        alert.style.display = "none";
    }
    , 5000);
}

// on doc read
document.addEventListener("DOMContentLoaded", function(event) {
//     console.log("ready");
    document.querySelectorAll('.copyClip').forEach(function(el) {

        console.log(el);
        el.addEventListener('click', function() {
            copyToClipboard(el);
        });
    });

    document.body.addEventListener("click", function(event) {
        if (event.target.classList.contains("wa-collapsible-title")) {
            var formId = event.target.getAttribute("data-form-id");
            var content = document.querySelector('.wa-collapsible-content[data-form-id="' + formId + '"]');
            if (content.style.display === "block") {
                content.style.display = "none";
                // add dashicons-arrow-up-alt2 class, remove dashicons-arrow-down-alt2 class
                var icon = event.target.querySelector("i");
                icon.classList.remove("dashicons-arrow-up-alt2");
                icon.classList.add("dashicons-arrow-down-alt2");
            } else {
                content.style.display = "block";
                // add dashicons-arrow-down-alt2 class, remove dashicons-arrow-up-alt2 class
                var icon = event.target.querySelector("i");
                icon.classList.remove("dashicons-arrow-down-alt2");
                icon.classList.add("dashicons-arrow-up-alt2");
            }
        }
    });
});