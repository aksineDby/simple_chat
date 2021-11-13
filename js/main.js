
$(function(){
    const chat = $("#messagePlace");
    window.input = $('#input');

    function wsStart() {
        window.ws = new WebSocket("ws://0.0.0.0:8000/");
        ws.onopen = function() {
            console.log('onopen');
        };
        ws.onclose = function() {
            console.log('onclose');
            setTimeout(wsStart, 1000);
        };
        ws.onmessage = function(evt) {
            console.log('onmessage');
            chat.append("<p>"+evt.data+"</p>");
            chat.scrollTop = chat[0].scrollHeight;
        };
    }
    wsStart();

    input.focus();

    $('#sendForm').submit(function(e){
        e.preventDefault();
        e.stopPropagation();
        window.ws.send(input.val());
        input.val('');
        return false;
    });

});
