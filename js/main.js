
$(function(){
    const chat = $("#messagePlace");
    const input = $('#input');
    const login = $('#login');
    const sendForm = $('#sendForm');
    const spinner = $('#chatWrapperLoading');

    let needClear = false;

    function wsStart() {
        window.ws = new WebSocket("ws://0.0.0.0:8000/");
        ws.onopen = function() {
            console.log('onopen');
        };
        ws.onclose = function() {
            console.log('onclose');
            setTimeout(wsStart, 1000);
            needClear = true;
            spinner.show();
        };
        ws.onmessage = function(evt) {
            console.log('onmessage');
            if (needClear) chat.empty();
            if (evt.data === 'close') {
                alert('sorry, limit of users.');
                ws.close();
            } else {
                chat.append("<p>"+evt.data+"</p>");
                chat.scrollTop = chat[0].scrollHeight;
                spinner.hide();
            }
        };
    }
    wsStart();

    login.val('Guest#' + parseInt(Math.random() * 100, 10));

    login.keydown(function(e){
        if (e.which === 13 || e.keyCode === 13) {
            //code to execute here
            return false;
        }
    });
    login.keyup(function(){
        let self = $(this);
        if (!self.val()) {
            self.addClass('invalid');
        } else if (self.hasClass('invalid')){
            self.removeClass('invalid');
        }
    });

    sendForm.submit(function(e){
        e.preventDefault();
        e.stopPropagation();
        if (login.val().length < 1) {
            return false;
        }
        let message = input.val();
        if (message.length > 140) {
            message = message.substr(0, 140);
        }
        let msg = {
            author: login.val(),
            message: message
        };
        let msgJson = JSON.stringify(msg);
        window.ws.send(msgJson);
        input.val('');
        return false;
    });

    input.keydown(function(e){
        if ((e.keyCode === 10 || e.keyCode === 13) && e.ctrlKey) {
            sendForm.submit();
        }
    });

    login.focus();
});
