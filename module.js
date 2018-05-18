M.modReservation = {};

M.modReservation.initView = function(Y) {
    Y.on('click', function() {
        Y.all('input.request').each(function() {
            this.set('checked', 'checked');
        });
    }, '#checkall');

    Y.on('click', function() {
        Y.all('input.request').each(function() {
            this.set('checked', '');
        });
    }, '#checknone');
};
