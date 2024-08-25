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
 * JavaScript for managing multiple grade items for a quiz.
 *
 * @module     mod_quiz/edit_multiple_grades
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import MoodleConfig from 'core/config';
import {addIconToContainer} from 'core/loadingicon';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {get_string as getString} from 'core/str';
import * as Templates from 'core/templates';
import Modal from 'core/modal';
import {prefetchStrings} from 'core/prefetch';
import Fragment from 'core/fragment';

/**
 * @type {Object} selectors used in this code.
 */
const SELECTORS = {
    'addGradeItemButton': '#mod_quiz-add_grade_item',
    'autoSetupButton': '#mod_quiz-grades_auto_setup',
    'editingPageContents': '#edit_grading_page-contents',
    'gradeItemList': 'table#mod_quiz-grade-item-list',
    'gradeItemSelect': 'select[data-slot-id]',
    'gradeItemSelectId': (id) => 'select#grade-item-choice-' + id,
    'gradeItemTr': 'table#mod_quiz-grade-item-list tr[data-quiz-grade-item-id]',
    'inplaceEditable': 'span.inplaceeditable',
    'inplaceEditableOn': 'span.inplaceeditable.inplaceeditingon',
    'resetAllButton': '#mod_quiz-grades_reset_all',
    'slotList': 'table#mod_quiz-slot-list',
    'updateGradeItemLink': (id) => 'tr[data-quiz-grade-item-id="' + id + '"] .quickeditlink',
};

prefetchStrings('mod_quiz', [
    'overallfeedback_for',
    'insertfeedbackbefore',
]);

let modal;

/**
 * Call the Ajax service to create a quiz grade item.
 *
 * @param {Number} quizId id of the quiz to update.
 * @returns {Promise<Object>} a promise that resolves to the template context required to re-render the page.
 */
const createGradeItem = (
    quizId,
) => callServiceAndReturnRenderingData({
    methodname: 'mod_quiz_create_grade_items',
    args: {
        quizid: quizId,
        quizgradeitems: [{name: ''}],
    }
});

/**
 * Call the Ajax service to update a quiz grade item.
 *
 * @param {Number} quizId id of the quiz to update.
 * @param {Number} gradeItemId id of the grade item to update.
 * @param {String} newName the new name to set.
 * @return {Promise} Promise that resolves to the context required to re-render the page.
 */
const updateGradeItem = (
    quizId,
    gradeItemId,
    newName
) => callServiceAndReturnRenderingData({
    methodname: 'mod_quiz_update_grade_items',
    args: {
        quizid: quizId,
        quizgradeitems: [{id: gradeItemId, name: newName}],
    }
});

/**
 * Call the Ajax service to delete a quiz grade item.
 *
 * @param {Number} quizId id of the quiz to update.
 * @param {Number} gradeItemId id of the grade item to delete.
 * @return {Promise} Promise that resolves to the context required to re-render the page.
 */
const deleteGradeItem = (
    quizId,
    gradeItemId
) => callServiceAndReturnRenderingData({
    methodname: 'mod_quiz_delete_grade_items',
    args: {
        quizid: quizId,
        quizgradeitems: [{id: gradeItemId}],
    }
});

/**
 * Call the Ajax service to update the quiz grade item used by a slot.
 *
 * @param {Number} quizId id of the quiz to update.
 * @param {Number} slotId id of the slot to update.
 * @param {Number|null} gradeItemId new grade item ot set, or null to un-set.
 * @return {Promise} Promise that resolves to the context required to re-render the page.
 */
const updateSlotGradeItem = (
    quizId,
    slotId,
    gradeItemId
) => callServiceAndReturnRenderingData({
    methodname: 'mod_quiz_update_slots',
    args: {
        quizid: quizId,
        slots: [{id: slotId, quizgradeitemid: gradeItemId}],
    }
});

/**
 * Call the Ajax service to setup one grade item for each quiz section.
 *
 * @param {Number} quizId id of the quiz to update.
 * @return {Promise} Promise that resolves to the context required to re-render the page.
 */
const autoSetupGradeItems = (
    quizId
) => callServiceAndReturnRenderingData({
    methodname: 'mod_quiz_create_grade_item_per_section',
    args: {
        quizid: quizId
    }
});

/**
 * Make a web service call, and also call mod_quiz_get_edit_grading_page_data to get the date to re-render the page.
 *
 * @param {Object} methodCall a web service call to pass to fetchMany. Must include methodCall.args.quizid.
 * @returns {Promise<Object>} a promise that resolves to the template context required to re-render the page.
 */
const callServiceAndReturnRenderingData = (methodCall) => callServicesAndReturnRenderingData([methodCall]);

/**
 * Make a web service call, and also call mod_quiz_get_edit_grading_page_data to get the date to re-render the page.
 *
 * @param {Object[]} methodCalls web service calls to pass to fetchMany. Must include methodCalls[0].args.quizid.
 * @returns {Promise<Object>} a promise that resolves to the template context required to re-render the page.
 */
const callServicesAndReturnRenderingData = (methodCalls) => {
    methodCalls.push({
            methodname: 'mod_quiz_get_edit_grading_page_data',
            args: {
                quizid: methodCalls[0].args.quizid,
            }
        });
    return Promise.all(fetchMany(methodCalls))
    .then(results => JSON.parse(results.at(-1)));
};

/**
 * A function to handle the event of adding overall feedback for each grade item.
 *
 * @param {Event} e click event.
 */
const handleGradeItemAddFeedback = async(e) => {
    e.preventDefault();
    const pending = new Pending('add-overall-feedback');
    const tableCell = e.target.closest('td');
    const tableRow = tableCell.closest('tr');
    const contextId = Number(e.target.closest('a').dataset.contextid);
    const quizId = tableRow.closest('table').dataset.quizId;
    const gradeItemId = tableRow.dataset.quizGradeItemId;
    const rawName = tableRow.querySelector('th span.inplaceeditable').dataset.rawName;

    // Create a modal instance contains the overall feedbacks.
    modal = await Modal.create({
        title: getString('overallfeedback_for', 'mod_quiz', rawName),
        body: '',
        show: true,
        removeOnClose: true,
        large: true,
        templateContext: {
            classes: 'overallfeedback',
        },
    });

    const modalBody = modal.getBody()[0];
    // Set the quiz ID and grade item ID in the modal body to allow retrieval later.
    modalBody.dataset.quizid = quizId;
    modalBody.dataset.gradeItemId = gradeItemId;
    // Show the loading icon.
    addIconToContainer(modalBody, pending);
    // Load existing overall feedback based on the specific grade item ID.
    // If no feedback exists, display a sample feedback with a boundary of 100%.
    Fragment.loadFragment('mod_quiz', 'load_overall_feedback_data', contextId, {
        quizId,
        gradeItemId,
    }).done(function(html, js) {
        // Set the overall feedback form data into the modal body.
        modal.setBody(html);
        // Wait until the HTML is fully appended to the DOM before running the JavaScript code.
        setTimeout(() => {
            Templates.runTemplateJS(js);
        }, 500);
        // Enable the footer buttons to allow user interaction with them.
        updateStatusFooterButton(false);
        // Add event for the divider.
        // After the template loads, events need to be added to all dividers to allow users to add more overall feedback.
        modalBody.querySelectorAll('.divider button.feedbackadd-button').forEach(addFeedback => {
            // Set a flag to mark that the event has already been attached.
            addFeedback.dataset.eventAttached = 'true';
            addFeedback.addEventListener('click', e => {
                e.preventDefault();
                handleAddMoreFeedback(e, modalBody, contextId);
            });
        });
        // The form's submit button will be handled by JavaScript, so we need to add an event to manage it.
        modalBody.querySelectorAll('.modal-footer input[type="submit"]').forEach(input => {
            input.addEventListener('click', handleSubmitModal);
        });

        pending.resolve();
    });
};

/**
 * A function to handle events when the user tries to click on the save or cancel button in a modal.
 *
 * @param {Event} e click event.
 */
const handleSubmitModal = (e) => {
    e.preventDefault();
    const target = e.currentTarget;
    const action = target.dataset.action;
    switch (action) {
        case "cancel":
            // Must destroy it to allow the editor JS to run when we reopen the modal.
            modal.destroy();
            break;
        case "save":
            // Validate and then save the feedback.
            saveFeedback();
            break;
        default:
            break;
    }
};

/**
 * Toggle the status of the footer buttons.
 *
 * @param {Boolean} status True indicates that the button is disabled..
 */
const updateStatusFooterButton = (status) => {
    modal.getBody()[0].querySelectorAll('.modal-footer input').forEach(inputEl => {
        inputEl.disabled = status;
    });
};

/**
 * Validate the form data and save the feedback if it's valid.
 */
const saveFeedback = async() => {
    // Browse through every element in the modal form and collect them into an object.
    const formData = collectFormData();
    updateStatusFooterButton(true);
    const gradeItemId = parseInt(modal.getBody()[0].dataset.gradeItemId);
    const options = {
        methodname: 'mod_quiz_save_overall_feedback_per_grade_item',
        args: {
            formdata: JSON.stringify(formData),
            quizid: parseInt(modal.getBody()[0].dataset.quizid),
            gradeitemid: parseInt(modal.getBody()[0].dataset.gradeItemId),
        },
    };

    try {
        // Validate form data and save it.
        const result = await fetchMany([options])[0];
        // Toggle error messages.
        const errors = JSON.parse(result.errors);
        displayErrors(errors);
        // If the form data is valid.
        if (errors.length === 0) {
            // Update icon and title for the menu-item.
            // Since the page does not reload, we must change it manually.
            const gradeItemRow = document.querySelector(SELECTORS.gradeItemList +
                ` tr[data-quiz-grade-item-id="${gradeItemId}"]`);
            const addOverallFeedbackMenu = gradeItemRow
                .querySelector('.moodle-actionmenu a.dropdown-item[data-action-add-feedback]');
            const {key, title, titleLevelFeedback} = await getIconFeedback(parseInt(result.total));
            const icon = await Templates.renderPix(key, 'core', title);
            addOverallFeedbackMenu.innerHTML = icon + title;
            gradeItemRow.querySelector('td.total-overallfeedback').innerText = titleLevelFeedback;
            // Must destroy it to allow the editor JS to run when we reopen the modal.
            modal.destroy();
        }
        updateStatusFooterButton(false);
    } catch (e) {
        updateStatusFooterButton(false);
        Notification.exception(e);
    }
};

/**
 * Prepare the type icon and title before rendering the icon for updating menu items in the overall feedback dropdown list.
 *
 * @param {Number} totalFeedback Number of feedback entries in the grade item.
 * @returns {Object} Return type of icon and title.
 */
const getIconFeedback = async(totalFeedback) => {
    let title = '';
    let titleLevelFeedback = '';
    let key = 't/edit';
    switch (totalFeedback) {
        case 0:
            title = await getString('addoverallfeedback', 'quiz');
            titleLevelFeedback = '-';
            key = 't/add';
            break;
        case 1:
            title = await getString('editoverallfeedback1level', 'quiz');
            titleLevelFeedback = await getString('overallfeedback1level', 'quiz', 1);
            break;
        default:
            title = await getString('editoverallfeedbacknlevels', 'quiz', totalFeedback);
            titleLevelFeedback = await getString('overallfeedbacknlevels', 'quiz', totalFeedback);
            break;
    }

    return {key, title, titleLevelFeedback};
};

/**
 * Display error messages for every form element that exists in the errors object.
 *
 * @param {Object} errors Errors object.
 */
const displayErrors = (errors) => {
    const body = modal.getBody()[0];
    let feedbackTextIndex = 0;

    // We need to go through all the input elements to display error messages
    // for invalid inputs and clear error messages for valid ones.
    body.querySelectorAll('form .fitem').forEach(el => {
        // Check the container type. It can be static, text (boundaries), or editor (feedback).
        const type = el.querySelector('[data-fieldtype]')?.dataset?.fieldtype;
        switch (type) {
            case 'text': {
                const inputBoundary = el.querySelector('input[name^="feedbackboundaries"]');
                const errorText = errors[inputBoundary.name];
                const feedback = inputBoundary.closest('.felement').querySelector('.invalid-feedback');
                if (errorText) {
                    // If an error occurs with the feedback boundaries input, we need to display an error message.
                    inputBoundary.classList.add('is-invalid');
                    inputBoundary.setAttribute('autofocus', true);
                    feedback.classList.add('d-block');
                    feedback.innerText = errorText;
                    inputBoundary.setAttribute('aria-describedby', feedback.id);
                } else {
                    // If the feedback boundaries input is valid, we need to remove the error message.
                    inputBoundary.classList.remove('is-invalid');
                    inputBoundary.removeAttribute('autofocus');
                    feedback.classList.remove('d-block');
                    feedback.innerText = '';
                }
            }
                break;
            case 'editor': {
                const textAreaFeedback = el.querySelector('textarea[name^="feedbacktext"]');
               // Skip the first editor when the boundary is 100%.
                if (textAreaFeedback.id === 'id_feedbacktext_0_text') {
                    break;
                }
                const errorText = errors[`feedbacktext[${feedbackTextIndex}]`];
                const feedbackEl = el.querySelector('.invalid-feedback');
                if (errorText) {
                    feedbackEl.innerText = errorText;
                    feedbackEl.classList.add('d-block');
                } else {
                    feedbackEl.innerText = '';
                    feedbackEl.classList.remove('d-block');
                }
                feedbackTextIndex++;
            }
                break;
            default:
                break;
        }
    });
};

/**
 * Collect form data in overall feedback form.
 *
 * @return {Array} Form data array.
 */
const collectFormData = () => {
    const items = modal.getBody()[0].querySelectorAll('form .fitem');
    const formData = [];
    const itemData = {};
    items.forEach((el) => {
        const type = el.querySelector('[data-fieldtype]')?.dataset?.fieldtype;
        if (type) {
            switch (type) {
                case 'static':
                    // It's just sample data. It doesn't need to be true grade data.
                    itemData.boundary = 11;
                    break;
                case 'text':
                    itemData.boundary = el.querySelector('input[name^="feedbackboundaries"]').value;
                    break;
                case 'editor':
                    itemData.feedback = {
                        itemid: el.querySelector('input[type="hidden"][name$="[itemid]"]').value,
                        format: el.querySelector('input[type="hidden"][name$="[format]"]').value,
                        text: el.querySelector('textarea[name^="feedbacktext"]').value,
                    };
                    formData.push({...itemData});
                    break;
                default:
                    break;
            }
        }
    });

    return formData;
};

/**
 * A function to handle adding more feedback in the overall feedback modal.
 *
 * @param {Event} e click event.
 * @param {HTMLElement} modalBody Modal body element.
 * @param {Number} contextId Context id number.
 */
const handleAddMoreFeedback = async(e, modalBody, contextId) => {
    const target = e.currentTarget;
    const {after} = target.dataset;
    const numberOfEditors = modalBody.querySelectorAll('textarea[name^=feedbacktext]').length;
    const gradeItemId = modal.getBody()[0].dataset.gradeItemId;
    const divider = modalBody.querySelector(`.modal-body .divider button[data-after="${after}"]`)
        .closest('.divider');
    Fragment.loadFragment('mod_quiz', 'load_overall_feedback_form', contextId, {
        after,
        no: numberOfEditors,
        gradeitemid: parseInt(gradeItemId),
    }).done(function(html, js) {
        divider.insertAdjacentHTML('afterend', html);
        Templates.runTemplateJS(js);
        recalculateFeedbackIndex(modalBody, after, contextId);
    });
};

/**
 * Every time a new feedback is added to the form, we need to calculate the order number and update it for all elements.
 *
 * @param {HTMLElement} modalBody Modal body element.
 * @param {Number} after The order number used to detect the position to add new feedback.
 * @param {Number} contextId Context id number.
 */
const recalculateFeedbackIndex = (modalBody, after, contextId) => {
    const gradeBoundaryEls = modalBody.querySelectorAll('input[name^="feedbackboundaries"]');
    const dividerButtonEls = modalBody.querySelectorAll('.divider button');
    gradeBoundaryEls.forEach((el, key) => {
        if (key >= after) {
            // Re-update id or label for every elements.
            const wrapItem = el.closest('.fitem');
            const label = wrapItem.querySelector('label[id^="id_feedbackboundaries_"]');
            const inputBoundary = wrapItem.querySelector('input[name^="feedbackboundaries"]');
            wrapItem.id = 'fitem_id_feedbackboundaries_' + key;
            label.id = `id_feedbackboundaries_${key}_label`;
            label.setAttribute('for', `id_feedbackboundaries_${key}`);
            inputBoundary.id = 'id_feedbackboundaries_' + key;
            inputBoundary.name = `feedbackboundaries[${key}]`;
            wrapItem.querySelector('.invalid-feedback').id = 'id_error_feedbackboundaries_' + key;
        }
    });

    // Re-update devider.
    dividerButtonEls.forEach((el, key) => {
        el.dataset.after = key;
        getString('insertfeedbackbefore', 'mod_quiz', {afterindex: key}).then(string => {
            el.setAttribute('aria-label', string);
            return true;
        }).catch(() => {
            return false;
        });
        // Add event for the new divider that was just loaded.
        if (el.dataset.eventAttached === 'false') {
            // Set a flag if the button already has an event added.
            el.dataset.eventAttached = 'true';
            el.addEventListener('click', e => {
                e.preventDefault();
                handleAddMoreFeedback(e, modalBody, contextId);
            });
        }
    });
};

/**
 * Handle click events on the delete icon.
 *
 * @param {Event} e click event.
 */
const handleGradeItemDelete = (e) => {
    e.preventDefault();
    const pending = new Pending('delete-quiz-grade-item');

    const tableCell = e.target.closest('td');
    addIconToContainer(tableCell, pending);

    const tableRow = tableCell.closest('tr');
    const quizId = tableRow.closest('table').dataset.quizId;
    const gradeItemId = tableRow.dataset.quizGradeItemId;

    let nextItemToFocus;
    if (tableRow.nextElementSibling) {
        nextItemToFocus = SELECTORS.updateGradeItemLink(tableRow.nextElementSibling.dataset.quizGradeItemId);
    } else {
        nextItemToFocus = SELECTORS.addGradeItemButton;
    }

    deleteGradeItem(quizId, gradeItemId)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(nextItemToFocus).focus();
        })
        .catch(Notification.exception);
};

/**
 *
 * @param {HTMLElement} editableSpan the editable to turn off.
 */
const stopEditingGadeItem = (editableSpan) => {
    editableSpan.innerHTML = editableSpan.dataset.oldContent;
    delete editableSpan.dataset.oldContent;

    editableSpan.classList.remove('inplaceeditingon');
    editableSpan.querySelector('[data-action-edit]').focus();
};

/**
 * Handle click events on the start rename icon.
 *
 * @param {Event} e click event.
 */
const handleGradeItemEditStart = (e) => {
    e.preventDefault();
    const pending = new Pending('edit-quiz-grade-item-start');
    const editableSpan = e.target.closest(SELECTORS.inplaceEditable);

    document.querySelectorAll(SELECTORS.inplaceEditableOn).forEach(stopEditingGadeItem);

    editableSpan.dataset.oldContent = editableSpan.innerHTML;
    getString('edittitleinstructions')
        .then((instructions) => {
            const uniqueId = 'gi-edit-input-' + editableSpan.closest('tr').dataset.quizGradeItemId;
            editableSpan.innerHTML = '<span class="editinstructions">' + instructions + '</span>' +
                    '<label class="sr-only" for="' + uniqueId + '">' + editableSpan.dataset.editLabel + '</label>' +
                    '<input type="text" id="' + uniqueId + '" value="' + editableSpan.dataset.rawName +
                            '" class="ignoredirty form-control w-100">';

            const inputElement = editableSpan.querySelector('input');
            inputElement.focus();
            inputElement.select();
            editableSpan.classList.add('inplaceeditingon');
            pending.resolve();
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Handle key down in the editable.
 *
 * @param {Event} e key event.
 */
const handleGradeItemKeyDown = (e) => {
    if (e.keyCode !== 13) {
        return;
    }

    const editableSpan = e.target.closest(SELECTORS.inplaceEditableOn);

    // Check this click is on a relevant element.
    if (!editableSpan || !editableSpan.closest(SELECTORS.gradeItemList)) {
        return;
    }

    e.preventDefault();
    const pending = new Pending('edit-quiz-grade-item-save');

    const newName = editableSpan.querySelector('input').value;
    const tableCell = e.target.closest('th');
    addIconToContainer(tableCell);

    const tableRow = tableCell.closest('tr');
    const quizId = tableRow.closest('table').dataset.quizId;
    const gradeItemId = tableRow.dataset.quizGradeItemId;

    updateGradeItem(quizId, gradeItemId, newName)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(SELECTORS.updateGradeItemLink(gradeItemId)).focus({'focusVisible': true});
        })
        .catch(Notification.exception);
};

/**
 * Replace the contents of the page with the page re-rendered from the provided data, once that promise resolves.
 *
 * @param {Object} editGradingPageData the template context data required to re-render the page.
 * @returns {Promise<void>} a promise that will resolve when the page is updated.
 */
const reRenderPage = (editGradingPageData) =>
    Templates.render('mod_quiz/edit_grading_page', editGradingPageData)
        .then((html, js) => Templates.replaceNode(document.querySelector(SELECTORS.editingPageContents), html, js || ''));

/**
 * Handle key up in the editable.
 *
 * @param {Event} e key event.
 */
const handleGradeItemKeyUp = (e) => {
    if (e.keyCode !== 27) {
        return;
    }

    const editableSpan = e.target.closest(SELECTORS.inplaceEditableOn);

    // Check this click is on a relevant element.
    if (!editableSpan || !editableSpan.closest(SELECTORS.gradeItemList)) {
        return;
    }

    e.preventDefault();
    stopEditingGadeItem(editableSpan);
};

/**
 * Handle focus out of the editable.
 *
 * @param {Event} e event.
 */
const handleGradeItemFocusOut = (e) => {
    if (MoodleConfig.behatsiterunning) {
        // Behat triggers focusout too often so ignore.
        return;
    }

    const editableSpan = e.target.closest(SELECTORS.inplaceEditableOn);

    // Check this click is on a relevant element.
    if (!editableSpan || !editableSpan.closest(SELECTORS.gradeItemList)) {
        return;
    }

    e.preventDefault();
    stopEditingGadeItem(editableSpan);
};

/**
 * Handle when the selected grade item for a slot is changed.
 *
 * @param {Event} e event.
 */
const handleSlotGradeItemChanged = (e) => {
    const select = e.target.closest(SELECTORS.gradeItemSelect);

    // Check this click is on a relevant element.
    if (!select || !select.closest(SELECTORS.slotList)) {
        return;
    }

    e.preventDefault();
    const pending = new Pending('edit-slot-grade-item-updated');

    const slotId = select.dataset.slotId;
    const newGradeItemId = select.value ? select.value : null;
    const tableCell = e.target.closest('td');
    addIconToContainer(tableCell, pending);

    const quizId = tableCell.closest('table').dataset.quizId;

    updateSlotGradeItem(quizId, slotId, newGradeItemId)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(SELECTORS.gradeItemSelectId(slotId)).focus();
        })
        .catch(Notification.exception);
};

/**
 * Handle clicks in the table the shows the grade items.
 *
 * @param {Event} e click event.
 */
const handleGradeItemClick = (e) => {
    const link = e.target.closest('a');

    // Check this click is on a relevant element.
    if (!link || !link.closest(SELECTORS.gradeItemList)) {
        return;
    }

    if (link.dataset.actionDelete) {
        handleGradeItemDelete(e);
    }

    if (link.dataset.actionEdit) {
        handleGradeItemEditStart(e);
    }

    if (link.dataset.actionAddFeedback) {
        handleGradeItemAddFeedback(e);
    }
};

/**
 * Handle clicks on the buttons.
 *
 * @param {Event} e click event.
 */

const handleButtonClick = (e) => {
    if (e.target.closest(SELECTORS.addGradeItemButton)) {
        handleAddGradeItemClick(e);
    }
    if (e.target.closest(SELECTORS.autoSetupButton)) {
        handleAutoSetup(e);
    }
    if (e.target.closest(SELECTORS.resetAllButton)) {
        handleResetAllClick(e);
    }
};

/**
 * Handle clicks on the 'Add grade item' button.
 *
 * @param {Event} e click event.
 */
const handleAddGradeItemClick = (e) => {
    e.preventDefault();
    const pending = new Pending('create-quiz-grade-item');
    addIconToContainer(e.target.parentNode, pending);

    const quizId = e.target.dataset.quizId;

    createGradeItem(quizId)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(SELECTORS.addGradeItemButton).focus();
        })
        .catch(Notification.exception);
};

/**
 * Handle clicks on the reset button - show a confirmation.
 *
 * @param {Event} e click event.
 */
const handleAutoSetup = (e) => {
    e.preventDefault();
    const pending = new Pending('setup-quiz-grade-items');

    const quizId = e.target.dataset.quizId;

    autoSetupGradeItems(quizId)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(SELECTORS.resetAllButton).focus();
        })
        .catch(Notification.exception);
};

/**
 * Handle clicks on the reset button - show a confirmation.
 *
 * @param {Event} e click event.
 */
const handleResetAllClick = (e) => {
    e.preventDefault();
    const button = e.target;

    Notification.deleteCancelPromise(
        getString('gradeitemsremoveallconfirm', 'quiz'),
        getString('gradeitemsremoveallmessage', 'quiz'),
        getString('reset'),
        button
    ).then(() => reallyResetAll(button))
    .catch(() => button.focus());
};

/**
 * Really reset all if the confirmation is OKed.
 *
 * @param {HTMLElement} button the reset button.
 */
const reallyResetAll = (button) => {
    const pending = new Pending('reset-quiz-grading');
    addIconToContainer(button.parentNode, pending);

    const quizId = button.dataset.quizId;

    let methodCalls = [];

    // Call to clear any assignments of grade items to slots (if required).
    const slotResets = [...document.querySelectorAll(SELECTORS.gradeItemSelect)].map(
            (select) => ({
                id: select.dataset.slotId,
                quizgradeitemid: 0,
            }));
    if (slotResets.length) {
        methodCalls.push({
            methodname: 'mod_quiz_update_slots',
            args: {
                quizid: quizId,
                slots: slotResets
            }
        });
    }

    // Request to delete all the grade items.
    methodCalls.push({
        methodname: 'mod_quiz_delete_grade_items',
        args: {
            quizid: quizId,
            quizgradeitems: [...document.querySelectorAll(SELECTORS.gradeItemTr)].map((tr) => {
                return {id: tr.dataset.quizGradeItemId};
            })
        }
    });

    callServicesAndReturnRenderingData(methodCalls)
        .then(reRenderPage)
        .then(() => {
            pending.resolve();
            document.querySelector(SELECTORS.addGradeItemButton).focus();
        })
        .catch(Notification.exception);
};

/**
 * Replace the container with a new version.
 */
const registerEventListeners = () => {
    document.body.addEventListener('click', handleGradeItemClick);
    document.body.addEventListener('keydown', handleGradeItemKeyDown);
    document.body.addEventListener('keyup', handleGradeItemKeyUp);
    document.body.addEventListener('focusout', handleGradeItemFocusOut);

    document.body.addEventListener('click', handleButtonClick);

    document.body.addEventListener('change', handleSlotGradeItemChanged);
};

/**
 * Entry point.
 */
export const init = () => {
    registerEventListeners();
};
