$(document).ready(function() {
    $('.plus').click(function() {
        var quantity = parseInt($('.qty').val());
        $('.qty').val(quantity + 1);
    });

    $('.minus').click(function() {
        var quantity = parseInt($('.qty').val());
        if (quantity > 1) {
            $('.qty').val(quantity - 1);
        }
    });
});

$(document).ready(function() {
    $('.plus').click(function() {
        var quantity = parseInt($('.qty').val());
        $('.qty').val(quantity + 1);
    });

    $('.minus').click(function() {
        var quantity = parseInt($('.qty').val());
        if (quantity > 1) {
            $('.qty').val(quantity - 1);
        }
    });
});
