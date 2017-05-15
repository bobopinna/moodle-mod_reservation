M.mod_reservation = {};

M.mod_reservation.init_view = function(Y) {
    Y.on('click', function(e) {
        Y.all('input.request').each(function() {
            this.set('checked', 'checked');
        });
    }, '#checkall');

    Y.on('click', function(e) {
        Y.all('input.request').each(function() {
            this.set('checked', '');
        });
    }, '#checknone');
};
