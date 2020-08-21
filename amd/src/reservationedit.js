// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Some UI stuff for edit page.
 *
 * @package    mod_reservation
 * @copyright  2019 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/templates', 'core/notification', 'core/ajax'],
function($, Str, ModalFactory, ModalEvents, Templates, Notification, Ajax) {

    var SELECTORS = {
        CLASHBUTTON: "#checkclashes",
        PLACEFIELD: "#id_locationtext",
        PLACESELECT: "#id_location",
        TIMESTART: "#id_timestart",
        TIMEEND: "#id_timeend",
        BULKFIELD: "select.field",
        BULKMATCHVALUE: "div.matchvalue input",
    };

    /**
     * Constructor
     *
     * @param {Object} options Object containing options.
     */
    var ReservationEdit = function(options) {
        this.courseId = options.courseid;

        this.attachEventListeners();
    };
    // Class variables and functions.

    /**
     * @var {int} courseId
     * @private
     */
    ReservationEdit.prototype.courseId = -1;

    /**
     * @var {Modal} modal
     * @private
     */
    ReservationEdit.prototype.modal = null;

    /**
     * Private method
     *
     * @method attachEventListeners
     * @private
     */
    ReservationEdit.prototype.attachEventListeners = function() {
        $(SELECTORS.BULKMATCHVALUE).on('focus', function(e) {
            $(e.target).blur();

            var matchvalueId = $(e.target).attr('id');
            this.showMatchvalues(matchvalueId).fail(Notification.exception);
        }.bind(this));

        $(SELECTORS.CLASHBUTTON).on('click', function() {
            this.showClashes().fail(Notification.exception);
        }.bind(this));

        $(SELECTORS.BULKFIELD).on('change', function(e) {
            var fieldId = $(e.target).attr('id');
            var matchvalueId = fieldId.replace('field', 'matchvalue');
            $("#" + matchvalueId).prop('value', '');
        });
    };

    /**
     * Show matchvalues popup
     *
     * @method showMatchvalues
     * @private
     * @param {string} matchvalueId
     * @return {Promise}
     */
    ReservationEdit.prototype.showMatchvalues = function(matchvalueId) {

        var fieldId = matchvalueId.replace('matchvalue', 'field');
        var fieldValue = $("#" + fieldId).val();

        if (fieldValue == '') {
            // Nothing to do.
            return $.Deferred().resolve().promise();
        }

        var stringPromise = Str.get_string('selectvalue', 'mod_reservation');
        var novaluesPromise = Str.get_string('novalues', 'mod_reservation');

        return $.when(
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
            }),
            stringPromise,
            novaluesPromise
        ).then(function(modal, title, body) {
            // Keep a reference to the modal.
            this.modal = modal;
            this.modal.setTitle(title);
            this.modal.setBody(body);

            body = Ajax.call([{
                methodname: 'mod_reservation_get_matchvalues',
                args: {
                    fieldname: fieldValue,
                    courseid: this.courseId
                }
            }])[0].then(function(results) {
                if (results.length > 0) {
                    var html = '<ul>';
                    for (var i = 0; i < results.length; i++) {
                        // Display values list.
                        var encodedresult = $('<div/>').text(results[i]).html();
                        html += '<li class="matchvalues">';
                        html += '<input type="radio" name="matchvalue" value="' + encodedresult + '" id="value_' + i + '" />';
                        html += '<label for="value_' + i + '">' + encodedresult + '</label>';
                        html += '</li>';
                    }
                    html += '</ul>';
                    this.modal.setBody(html);
                    return true;
                }
            }.bind(this)).catch(Notification.exception);

            // We want to focus on the field select when the dialog is closed.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                $("#" + fieldId).focus();
                this.modal.getRoot().remove();
            }.bind(this));

            this.modal.getRoot().on(ModalEvents.save, function() {
                var matchvalue = $("input[name='matchvalue']:checked").val();
                $('#' + matchvalueId).prop('value', matchvalue);
            });

            this.modal.show();

            return this.modal;
        }.bind(this));
    };

    /**
     * Show clashes popup
     *
     * @method showClashes
     * @private
     * @return {Promise}
     */
    ReservationEdit.prototype.showClashes = function() {

        var place = $(SELECTORS.PLACESELECT).val();
        if ((typeof place === 'undefined') || (place == 0)) {
            place = $(SELECTORS.PLACEFIELD).val();
        }
        var timeStartDay = $(SELECTORS.TIMESTART + '_day').val();
        var timeStartMonth = $(SELECTORS.TIMESTART + '_month').val();
        var timeStartYear = $(SELECTORS.TIMESTART + '_year').val();
        var timeStartHour = $(SELECTORS.TIMESTART + '_hour').val();
        var timeStartMinute = $(SELECTORS.TIMESTART + '_minute').val();
        var timeStart = timeStartYear + '-' + timeStartMonth + '-' + timeStartDay + '-' + timeStartHour + '-' + timeStartMinute;
        var timeEnd = '';
        if ($(SELECTORS.TIMEEND + '_enabled').is(':checked')) {
            var timeEndDay = $(SELECTORS.TIMEEND + '_day').val();
            var timeEndMonth = $(SELECTORS.TIMEEND + '_month').val();
            var timeEndYear = $(SELECTORS.TIMEEND + '_year').val();
            var timeEndHour = $(SELECTORS.TIMEEND + '_hour').val();
            var timeEndMinute = $(SELECTORS.TIMEEND + '_minute').val();
            timeEnd = timeEndYear + '-' + timeEndMonth + '-' + timeEndDay + '-' + timeEndHour + '-' + timeEndMinute;
        }
        var reservationId = $("input[name='instance']").val();

        var stringPromise = Str.get_string('clashesreport', 'mod_reservation');
        var noclashesPromise = Str.get_string('noclashes', 'mod_reservation');

        return $.when(
            ModalFactory.create({
                type: ModalFactory.types.CANCEL,
            }),
            stringPromise,
            noclashesPromise
        ).then(function(modal, title, body) {
            // Keep a reference to the modal.
            this.modal = modal;
            this.modal.setTitle(title);
            this.modal.setBody(body);

            body = Ajax.call([{
                methodname: 'mod_reservation_get_clashes',
                args: {
                    courseid: this.courseId,
                    place: place,
                    timestart: timeStart,
                    timeend: timeEnd,
                    reservationid: reservationId,
                }
            }])[0].then(function(result) {
                if (result !== null) {
                    this.modal.setBody(result);
                    return true;
                }
            }.bind(this)).catch(Notification.exception);

            // We want to focus on the field select when the dialog is closed.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                $(SELECTORS.CLASHBUTTON).focus();
                this.modal.getRoot().remove();
            }.bind(this));

            this.modal.show();

            return this.modal;
        }.bind(this));
    };

    return /** @alias module:mod_reservation/reservationedit */ {
        // Public variables and functions.

        /**
         * Initialise the unified reservation filter.
         *
         * @method init
         * @param {Object} options - List of options.
         * @return {ReservationEdit}
         */
        'init': function(options) {
            return new ReservationEdit(options);
        }
    };
});
