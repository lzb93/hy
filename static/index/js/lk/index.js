setInterval("ajaxpro()",500);
function ajaxpro(){
     var geturl = "/index/index/ajaxindexpro";
     var type = '';
    $.get(geturl,function(data){
        if (data) {
            data = jQuery.parseJSON(Base64.decode(data)); 
            $.each(data,function(k,v){
                
                
                $('#pid'+v.pid+' .prtitle').html(v.ptitle);
                $('#pid'+v.pid+' .now-value').html(v.Price);
                $('#pid'+v.pid+' .rise-low').html(v.Low);
                $('#pid'+v.pid+' .rise-high').html(v.High);
                
                if(v.isup == 1){

                    $('#pid'+v.pid+' .now-value').addClass('rise-value');
                    $('#pid'+v.pid+' .now-value').removeClass('fall-value');

                    $('#pid'+v.pid+' .rise-low').addClass('rise');
                    $('#pid'+v.pid+' .rise-low').removeClass('fall');

                    $('#pid'+v.pid+' .rise-high').addClass('rise');
                    $('#pid'+v.pid+' .rise-high').removeClass('fall');

                }else if(v.isup == 0){
                    $('#pid'+v.pid+' .now-value').removeClass('rise-value');
                    $('#pid'+v.pid+' .now-value').addClass('fall-value');

                    $('#pid'+v.pid+' .rise-low').removeClass('rise');
                    $('#pid'+v.pid+' .rise-low').addClass('fall');

                    $('#pid'+v.pid+' .rise-high').removeClass('rise');
                    $('#pid'+v.pid+' .rise-high').addClass('fall');
                }
                

            });
        }

    });
}
   