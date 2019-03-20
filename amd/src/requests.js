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
 * Some UI stuff for requests page.
 *
 * @package    mod_reservation
 * @copyright  2017 Damyon Wiese
 * @copyright  2019 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/templates', 'core/notification', 'core/ajax'],
function($, Str, ModalFactory, ModalEvents, Templates, Notification, Ajax) {

    var SELECTORS = {
        BULKACTIONSELECT: "#menuaction",
        BULKREQUESTCHECKBOXES: "input.request",
        BULKREQUESTNOSCHECKBOXES: "input.request[value='0']",
        BULKREQUESTSELECTEDCHECKBOXES: "input.request:checked",
        BULKACTIONFORM: "#requestactions",
        CHECKALLBUTTON: "#checkall",
        CHECKNONEBUTTON: "#checknone"
    };

    /**
     * Constructor
     *
     * @param {Object} options Object containing options.
     */
    var Requests = function(options) {

        this.reservationId = options.reservationid;

        this.attachEventListeners();
    };
    // Class variables and functions.

    /**
     * @var {Modal} modal
     * @private
     */
    Requests.prototype.modal = null;

    /**
     * @var {int} reservationId
     * @private
     */
    Requests.prototype.reservationId = -1;

    /**
     * Private method
     *
     * @method attachEventListeners
     * @private
     */
    Requests.prototype.attachEventListeners = function() {
        $(SELECTORS.BULKACTIONSELECT).on('change', function(e) {
            var action = $(e.target).val();
            if (action.indexOf('#') !== -1) {
                e.preventDefault();

                var ids = [];
                $(SELECTORS.BULKREQUESTSELECTEDCHECKBOXES).each(function(index, ele) {
                    var name = $(ele).attr('name');
                    var id = $(ele).attr('value');
                    ids.push(id);
                });
                if (action == '#messageselect') {
                    this.showSendMessage(ids).fail(Notification.exception);
                }
                $(SELECTORS.BULKACTIONSELECT).prop('selectedIndex', 0);
            } else if (action !== '') {
                if ($(SELECTORS.BULKREQUESTSELECTEDCHECKBOXES).length > 0) {
                    if (action == 'deleterequest') {
                        this.confirmDeleteRequests($(SELECTORS.BULKREQUESTSELECTEDCHECKBOXES).length).fail(Notification.exception);
                    } else {
                        $(SELECTORS.BULKACTIONFORM).submit();
                    }
                } else {
                    $(SELECTORS.BULKACTIONSELECT).prop('selectedIndex', 0);
                }
            }
        }.bind(this));

        $(SELECTORS.CHECKALLBUTTON).on('click', function() {
            $(SELECTORS.BULKREQUESTCHECKBOXES).prop('checked', true);
        });

        $(SELECTORS.CHECKNONEBUTTON).on('click', function() {
            $(SELECTORS.BULKREQUESTCHECKBOXES).prop('checked', false);
        });
    };

    /**
     * Show the deletion confirm popup
     *
     * @method confirmDeleteRequests
     * @private
     * @param {int} requestnumber
     * @return {Promise}
     */
    Requests.prototype.confirmDeleteRequests = function(requestnumber) {
        if (requestnumber == 0) {
            // Nothing to do.
            return $.Deferred().resolve().promise();
        }

        var stringPromise = Str.get_string('confirmdelete', 'mod_reservation');

        return $.when(
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
            }),
            stringPromise
        ).then(function(modal, body) {
            // Keep a reference to the modal.
            this.modal = modal;
            this.modal.setBody(body);
            var yes = Str.get_string('yes', 'core_moodle');
            this.modal.setSaveButtonText(yes);

            // We want to focus on the action select when the dialog is closed.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                $(SELECTORS.BULKACTIONSELECT).focus();
                this.modal.getRoot().remove();
            }.bind(this));

            this.modal.getRoot().on(ModalEvents.yes, function(){
                $(SELECTORS.BULKACTIONFORM).submit();
            });

            this.modal.show();

            return this.modal;
        }.bind(this));
    };

    /**
     * Show the send message popup.
     *
     * @method showSendMessage
     * @private
     * @param {int[]} requests
     * @return {Promise}
     */
    Requests.prototype.showSendMessage = function(requests) {

        if (requests.length == 0) {
            // Nothing to do.
            return $.Deferred().resolve().promise();
        }
        var titlePromise = null;
        if (requests.length == 1) {
            titlePromise = Str.get_string('sendbulkmessagesingle', 'core_message');
        } else {
            titlePromise = Str.get_string('sendbulkmessage', 'core_message', requests.length);
        }

        return $.when(
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                body: Templates.render('core_user/send_bulk_message', {})
            }),
            titlePromise
        ).then(function(modal, title) {
            // Keep a reference to the modal.
            this.modal = modal;

            this.modal.setTitle(title);
            this.modal.setSaveButtonText(title);

            // We want to focus on the action select when the dialog is closed.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                $(SELECTORS.BULKACTIONSELECT).focus();
                this.modal.getRoot().remove();
            }.bind(this));

            this.modal.getRoot().on(ModalEvents.save, this.submitSendMessage.bind(this, requests));

            this.modal.show();

            return this.modal;
        }.bind(this));
    };

    /**
     * Send a message to these requests.
     *
     * @method submitSendMessage
     * @private
     * @param {int[]} requests
     * @param {Event} e Form submission event.
     * @return {Promise}
     */
    Requests.prototype.submitSendMessage = function(requests) {

        var messageText = this.modal.getRoot().find('form textarea').val();

        var messages = [],
            i = 0;

        Ajax.call([{
            methodname: 'mod_reservation_get_requests_users',
            args: {
                reservationid: this.reservationId,
                requestids: requests
            }
        }])[0].then(function(users) {
            for (i = 0; i < users.length; i++) {
                 messages.push({touserid: users[i], text: messageText});
            }
            return true;
        }).then(function() {
            return Ajax.call([{
                methodname: 'core_message_send_instant_messages',
                args: {messages: messages}
            }])[0].then(function(messageIds) {
                if (messageIds.length == 1) {
                    return Str.get_string('sendbulkmessagesentsingle', 'core_message');
                } else {
                    return Str.get_string('sendbulkmessagesent', 'core_message', messageIds.length);
                }
            }).then(function(msg) {
                Notification.addNotification({
                    message: msg,
                    type: "success"
                });

                return true;
            }).catch(Notification.exception);
        }).catch(Notification.exception);
    };

    return /** @alias module:mod_reservation/requests */ {
        // Public variables and functions.

        /**
         * Initialise the unified request filter.
         *
         * @method init
         * @param {Object} options - List of options.
         * @return {Requests}
         */
        'init': function(options) {
            return new Requests(options);
        }
    };
});
