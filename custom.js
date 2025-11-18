var base_url = window.location.protocol+'//'+window.location.hostname+'/';

$('[data-toggle="tooltip"]').tooltip();

$(".loading").on("click", function() { $(".bg").show(); });

// date picker
$('.datepicker').datetimepicker({ format: 'YYYY-MM-DD' });

$('.select2').select2({ theme: 'bootstrap4' })

// image preview
$('.custom-file-input').on('change', function() {
    $('.custom-file-label').html($(this).get(0).files[0].name);

    var file = new FileReader();
    file.readAsDataURL($(this).get(0).files[0]);
    file.onload = function(e) { $('.img-preview').attr('src', e.target.result); }
})


// pilih bayar
$(".id_bayar").on("change", function(){
    var jenis           = $('option:selected', this).attr('jenis');
    var id_siswakelas   = $('option:selected', this).attr('id_siswakelas');
    var id_bayar        = $('option:selected', this).val();
    
    $.ajax({
        type:'post',
        url:'/ajax/nominal',
        data:'id_bayar='+id_bayar+'&jenis='+jenis+'&id_siswakelas='+id_siswakelas,
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(nominal) { $("input[name=nominal_pembayaran]").val(nominal); }
    })
    
    $.ajax({
        type:'post',
        url:'/ajax/bayar',
        data:'id_bayar='+id_bayar+'&jenis='+jenis+'&id_siswakelas='+id_siswakelas,
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(hasil) { $(".letak-untuk-bayar").html(hasil); }
    })
    
    $.ajax({
        type:'post',
        url:'/ajax/angsur',
        data:'id_bayar='+id_bayar+'&jenis='+jenis+'&id_siswakelas='+id_siswakelas,
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(res) { $(".letak-untuk-angsur").html(res); }
    })
})

/* Fungsi formatRupiah */
function formatRupiah(angka, prefix) {
    var number_string = angka.toString().replace(/[^,\d]/g, ""),
    split = number_string.split(","),
    sisa = split[0].length % 3,
    rupiah = split[0].substr(0, sisa),
    ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        separator = sisa ? "." : "";
        rupiah += separator + ribuan.join(".");
    }

    rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
    return prefix == undefined ? rupiah : rupiah ? "Rp. " + rupiah : "";
}
function formatRupiahNeg(angka, prefix) {
    // Check if the number is negative
    var isNegative = angka < 0;

    // Convert the number to a string and remove non-numeric characters
    var number_string = Math.abs(angka).toString().replace(/[^,\d]/g, ""),
        split = number_string.split(","),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    // Format the number with thousand separators
    if (ribuan) {
        separator = sisa ? "." : "";
        rupiah += separator + ribuan.join(".");
    }

    // Add the decimal part if it exists
    rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;

    // Add the negative sign if the number is negative
    if (isNegative) {
        rupiah = "-" + rupiah;
    }

    // Return the formatted string with or without the prefix
    return prefix == undefined ? rupiah : rupiah ? "Rp. " + rupiah : "";
}


$('.tahunajaran').on('change', function() {
    $.ajax({
        type:'POST',
        url:base_url+'ajax/tahun',
        data: 'tahun='+$(this).val(),
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(result) { location.reload(); }
    });
})

$('.semester').on('change', function() {
    $.ajax({
        type:'POST',
        url:base_url+'ajax/semester',
        data: 'semester='+$(this).val(),
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(result) { location.reload(); }
    });
})

$('.pop-up').on('click', function() {
    var action = $(this).data("act");
    var table = $(this).data("tab");

    var title = action.charAt(0).toUpperCase() + action.slice(1) + ' Data';

    $('.modal-title').html(title);

    if (action == 'ubah')
    {
        $('#form').prepend('<input type="hidden" name="id_'+table+'">');
        $('.action').removeClass('tambah');
        $('.action').addClass('ubah');
    }
    else
    {
        $('#form input[name=id_'+table+']').remove();
        $('.action').removeClass('ubah');
        $('.action').addClass('tambah');

        $('#form').trigger("reset");
    }
})

function swal(pesan)
{
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: pesan.toLowerCase().includes('berhasil') ? 'success' : 'error',
        title: '&nbsp;&nbsp;'+pesan,
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })

}

function notifikasi(validasi) {
    if (validasi != '')
    {
        time = 0;
        validasi.forEach(function (v, key) {
            setTimeout(function () {
                if (v !== '' && v !== undefined)
                {
                    $.notify({ icon: 'fas fa-exclamation-triangle', message: '&nbsp;&nbsp;'+v },
                    {
                        type: 'warning',
                        allow_dismiss: true,
                        placement: { from: 'top', align: 'right' },
                        z_index: 1051,
                        timer: 500+time,
                        animate: { enter: 'animate__animated animate__fadeInDown', exit: 'animate__animated animate__fadeOutUp' },
                    });
                }
            }, key * 500);
            time += 500;
        });
    }
}

function formulir(controller, method, form='form') {
    $.ajax({
        type:'POST',
        enctype:'multipart/form-data',
        url:base_url+controller+'/'+method,
        data: new FormData($('#'+form)[0]),
        processData:false,
        contentType: false,
        cache: false,
        dataType:'json',
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(valid) { valid.pesan == "sukses" ? location.reload() : notifikasi(valid.validasi); }
    });
}
function formulir_redirect(controller, method, form='form') {
    $.ajax({
        type:'POST',
        enctype:'multipart/form-data',
        url:base_url+controller+'/'+method,
        data: new FormData($('#'+form)[0]),
        processData:false,
        contentType: false,
        cache: false,
        dataType:'json',
        beforeSend: function() { $(".bg").show(); },
        complete: function() { $(".bg").hide(); },
        success:function(valid) {
            if (valid.pesan == "sukses") {
                window.open(valid.url, '_blank');
                location.reload();
            } else {
                notifikasi(valid.validasi)
            }
        }
    });
}

function hapus(href)
{
    Swal.fire({
        title: 'Apakah Anda Yakin?',
        text: "Data akan dihapus!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonClass: 'btn btn-purple mr-1',
        cancelButtonClass: 'btn btn-danger',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Tidak',
        buttonsStyling: false,
    }).then((result) => { if (result.isConfirmed) { document.location.href = href; } })
}
