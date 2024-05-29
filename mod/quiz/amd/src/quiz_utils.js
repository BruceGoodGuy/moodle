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
 * Render the question slot template for each question in the quiz edit view.
 *
 * @module     mod_quiz/quiz_utils
 * @copyright  2024 The Open University.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {config} from 'mod_quiz/quiz_toolboxes';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';

// const allStrings = [
//     {key: 'question', component: 'moodle'},
//     {key: 'removepagebreak', component: 'quiz'},
//     {key: 'addpagebreak', component: 'quiz'},
//     {key: 'questiondependencyremove', component: 'quiz', },
//     {key: '', component: ''},
//     {key: '', component: ''},
//     {key: '', component: ''},
//     {key: '', component: ''},
//     {key: '', component: ''},
// ];
// getStrings()

const slot = {
    CSS: {
        SLOT: 'slot',
        QUESTIONTYPEDESCRIPTION: 'qtype_description',
        CANNOT_DEPEND: 'question_dependency_cannot_depend'
    },
    CONSTANTS: {
        SLOTIDPREFIX: 'slot-',
        QUESTION: M.util.get_string('question', 'moodle')
    },
    SELECTORS: {
        SLOT: 'li.slot',
        INSTANCENAME: '.instancename',
        NUMBER: 'span.slotnumber',
        PAGECONTENT: 'div#page-content',
        PAGEBREAK: 'span.page_split_join_wrapper',
        ICON: '.icon',
        QUESTIONTYPEDESCRIPTION: '.qtype_description',
        SECTIONUL: 'ul.section',
        DEPENDENCY_WRAPPER: '.question_dependency_wrapper',
        DEPENDENCY_LINK: '.question_dependency_wrapper .cm-edit-action',
        DEPENDENCY_ICON: '.question_dependency_wrapper .icon'
    },

    /**
     * Retrieve the slot item from one of its child Nodes.
     *
     * @method getSlotFromComponent
     * @param slotComponent {Element} The component Node.
     * @return {Element|null} The Slot Node.
     */
    getSlotFromComponent: function(slotComponent) {
        return slotComponent.closest(this.SELECTORS.SLOT);
    },

    /**
     * Determines the slot ID for the provided slot.
     *
     * @method getId
     * @param slot {Element} The slot to find an ID for.
     * @return {Number|false} The ID of the slot in question or false if no ID was found.
     */
    getId: function(slot) {
        // We perform a simple substitution operation to get the ID.
        let id = slot.id.replace(
            this.CONSTANTS.SLOTIDPREFIX, '');

        // Attempt to validate the ID.
        id = parseInt(id, 10);
        if (typeof id === 'number' && isFinite(id)) {
            return id;
        }
        return false;
    },

    /**
     * Determines the slot name for the provided slot.
     *
     * @method getName
     * @param slot {Element} The slot to find a name for.
     * @return {string|null} The name of the slot in question or null if no name was found.
     */
    getName: function(slot) {
        const instance = slot.querySelector(this.SELECTORS.INSTANCENAME);
        if (instance) {
            // ??
            return instance.firstChild.data;
        }
        return null;
    },

    /**
     * Determines the slot number for the provided slot.
     *
     * @method getNumber
     * @param slot {Element} The slot to find the number for.
     * @return {Number|false} The number of the slot in question or false if no number was found.
     */
    getNumber(slot) {
        if (!slot) {
            return false;
        }
        // We perform a simple substitution operation to get the number.
        let number = slot.querySelector(this.SELECTORS.NUMBER).textContent.replace(
            this.CONSTANTS.QUESTION, '');
        // Attempt to validate the ID.
        number = parseInt(number, 10);
        if (!isNaN(number)) {
            return number;
        }
        return false;
    },

    /**
     * Updates the slot number for the provided slot.
     *
     * @method setNumber
     * @param slot {Element} The slot to update the number for.
     * @param number {Number} The slot number.
     * @return void
     */
    setNumber(slot, number) {
        let numberNode = slot.querySelector(this.SELECTORS.NUMBER);
        numberNode.innerHTML = '<span class="accesshide">' + this.CONSTANTS.QUESTION + '</span> ' + number;
    },

    /**
     * Returns a list of all slot elements on the page.
     *
     * @method getSlots
     * @return {Element[]} An array containing slot nodes.
     */
    getSlots() {
        return document.querySelectorAll(this.SELECTORS.PAGECONTENT + ' ' + this.SELECTORS.SECTIONUL + ' ' + this.SELECTORS.SLOT);
    },

    /**
     * Returns a list of all slot elements on the page that have numbers. Excudes description questions.
     *
     * @method getSlots
     * @return {Element[]} An array containing slot nodes.
     */
    getNumberedSlots() {
        let selector = this.SELECTORS.PAGECONTENT + ' ' + this.SELECTORS.SECTIONUL;
        selector += ' ' + this.SELECTORS.SLOT + ':not(' + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ')';
        return document.querySelectorAll(selector);
    },

    /**
     * Returns the previous slot to the given slot.
     *
     * @param {HTMLElement} slot - Slot node
     * @return {HTMLElement|false} The previous slot node or false.
     */
     getPrevious: function(slot) {
        let previousSlot = slot.previousElementSibling;
        while (previousSlot) {
            if (previousSlot.matches(this.SELECTORS.SLOT)) {
                return previousSlot;
            }
            previousSlot = previousSlot.previousElementSibling;
        }
        return false;
    },

    /**
     * Returns the previous numbered slot to the given slot.
     * Ignores slots containing description question types.
     *
     * @method getPrevious
     * @param slot {Element} Slot node
     * @return {Element|false} The previous slot node or false.
     */
    getPreviousNumbered(slot) {
        let previous = slot.previousElementSibling;
        while (previous) {
            if (!previous.matches(this.SELECTORS.SLOT + ':not(' + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ')')) {
                previous = previous.previousElementSibling;
            } else {
                return previous;
            }
        }

        let section = slot.closest('li.section').previousElementSibling;
        while (section) {
            const questions = section.querySelectorAll(this.SELECTORS.SLOT + ':not(' + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ')');
            if (questions.length > 0) {
                return questions[questions.length - 1];
            }
            section = section.previousElementSibling;
        }
        return false;
    },

    /**
     * Reset the order of the numbers given to each slot.
     *
     * @method reorderSlots
     * @return void
     */
    reorderSlots() {
        // Get list of slot nodes.
        const slots = this.getSlots();

        // Loop through slots incrementing the number each time.
        slots.forEach(slot => {
            if (!page.getPageFromSlot(slot)) {
                // Move the next page to the front.
                const nextPage = slot.nextElementSibling;
                slot.parentNode.insertBefore(nextPage, slot);
            }

            const previousSlot = this.getPreviousNumbered(slot);
            let previousSlotNumber = 0;

            if (slot.classList.contains(this.CSS.QUESTIONTYPEDESCRIPTION)) {
                return;
            }

            if (previousSlot) {
                previousSlotNumber = this.getNumber(previousSlot);
            }

            // Set slot number.
            this.setNumber(slot, previousSlotNumber + 1);
        });
    },

    /**
     * Add class only-has-one-slot to those sections that need it.
     *
     * @method updateOneSlotSections
     * @return void
     */
    updateOneSlotSections() {
        document.querySelectorAll('.mod-quiz-edit-content ul.slots li.section').forEach(section => {
            if (section.querySelectorAll(this.SELECTORS.SLOT).length > 1) {
                section.classList.remove('only-has-one-slot');
            } else {
                section.classList.add('only-has-one-slot');
            }
        });
    },

    /**
     * Remove a slot and related elements from the list of slots.
     *
     * @method remove
     * @param slot {Element} Slot node.
     * @return void
     */
    remove: function(slot) {
        const pageElement = page.getPageFromSlot(slot);
        slot.remove();

        // Is the page empty.
        if (!page.isEmpty(pageElement)) {
            return;
        }
        // If so remove it. Including add menu and page break.
        page.remove(pageElement, false);
    },

    /**
     * Returns a list of all page break elements on the page.
     *
     * @method getPageBreaks
     * @return {Element[]} An array containing page break nodes.
     */
    getPageBreaks() {
        let selector = this.SELECTORS.PAGECONTENT + ' ' + this.SELECTORS.SECTIONUL;
        selector += ' ' + this.SELECTORS.SLOT + this.SELECTORS.PAGEBREAK;
        return document.querySelectorAll(selector);
    },

    /**
     * Retrieve the page break element item from the given slot.
     *
     * @method getPageBreak
     * @param slot {Element} Slot node
     * @return {Element|null} The Page Break Node.
     */
    getPageBreak: function(slot) {
        return slot.querySelector(this.SELECTORS.PAGEBREAK);
    },

    /**
     * Add a page break and related elements to the list of slots.
     *
     * @method addPageBreak
     * @param slot {Element} Slot node.
     * @return pagebreak PageBreak node
     */
    addPageBreak(slot) {
        let nodeText = config.addpageiconhtml;
        nodeText = nodeText.replace('%%SLOT%%', this.getNumber(slot));
        let pageBreak = document.createElement('div');
        pageBreak.innerHTML = nodeText;
        slot.querySelector('div').insertAdjacentElement('afterend', pageBreak);
        return pageBreak;
    },

    /**
     * Remove a pagebreak from the given slot.
     *
     * @method removePageBreak
     * @param slot {Element} Slot node.
     * @return boolean
     */
    removePageBreak: function(slot) {
        const pageBreak = this.getPageBreak(slot);
        if (!pageBreak) {
            return false;
        }
        pageBreak.remove();
        return true;
    },

    /**
     * Reorder each pagebreak by iterating through each related slot.
     *
     * @method reorderPageBreaks
     * @return void
     */
    reorderPageBreaks: function() {
        // Get list of slot nodes.
        const slots = this.getSlots();
        let slotNumber = 0;

        slots.forEach((slot, key) => {
            slotNumber++;
            let pageBreak = this.getPageBreak(slot);
            const nextItem = slot.nextElementSibling && slot.nextElementSibling.matches('li.activity')
                ? slot.nextElementSibling : null;
            if (!nextItem) {
                // Last slot in a section. Should not have an icon.
                return;
            }

            // No pagebreak and not last slot. Add one.
            if (!pageBreak) {
                pageBreak = this.addPageBreak(slot);
            }

            // Remove last page break if there is one.
            if (pageBreak && key === slots.length - 1) {
                this.removePageBreak(slot);
            }

            // Get page break anchor element.
            const pageBreakLink = pageBreak.firstElementChild;

            // Get the correct title.
            let action = '';
            let iconName = '';
            if (page.isPage(nextItem)) {
                action = 'removepagebreak';
                iconName = 'e/remove_page_break';
            } else {
                action = 'addpagebreak';
                iconName = 'e/insert_page_break';
            }

            // Update the link and image titles
            pageBreakLink.title = M.util.get_string(action, 'quiz');
            pageBreakLink.dataset.action = action;

            // Update the image title.
            const icon = pageBreakLink.querySelector(this.SELECTORS.ICON);
            icon.title = M.util.get_string(action, 'quiz');
            icon.alt = M.util.get_string(action, 'quiz');

            // Update the image src.
            icon.src = M.util.image_url(iconName);

            // Get anchor url parameters as an associative array.
            const params = new URLSearchParams(pageBreakLink.href);
            // Update slot number.
            params.set('slot', slotNumber + '');
            // Update the anchor.
            pageBreakLink.href = `${pageBreakLink.href.split('?')[0]}?${params.toString()}`;
        });
    },

    /**
     * Update the dependency icons.
     *
     * @method updateAllDependencyIcons
     * @return void
     */
     updateAllDependencyIcons: function() {
        // Get list of slot nodes.
        const slots = this.getSlots();
        let slotNumber = 0;
        let previousSlot = null;

        // Loop through slots incrementing the number each time.
        slots.forEach(slot => {
            slotNumber++;

            const dependencyWrapper = slot.querySelector(this.SELECTORS.DEPENDENCY_WRAPPER);
            if (slotNumber === 1 || previousSlot?.dataset.canfinish === '0') {
                dependencyWrapper.classList.add(this.CSS.CANNOT_DEPEND);
            } else {
                dependencyWrapper.classList.remove(this.CSS.CANNOT_DEPEND);
            }
            this.updateDependencyIcon(slot, null);

            previousSlot = slot;
        });
    },

    /**
     * Update the slot icon to indicate the new requiresPrevious state.
     *
     * @param {HTMLElement} slot - Slot node
     * @param {boolean|null} requiresPrevious - Whether this node now requires the previous one.
     * @return void
     */
    updateDependencyIcon: function(slot, requiresPrevious) {
        const link = slot.querySelector(this.SELECTORS.DEPENDENCY_LINK);
        const icon = slot.querySelector(this.SELECTORS.DEPENDENCY_ICON);
        const previousSlot = this.getPrevious(slot);
        const a = {thisq: this.getNumber(slot)};

        if (previousSlot) {
            a.previousq = this.getNumber(previousSlot);
        }

        if (requiresPrevious === null) {
            requiresPrevious = link.dataset.action === 'removedependency';
        }

        if (requiresPrevious) {
            link.title = M.util.get_string('questiondependencyremove', 'quiz', a);
            link.dataset.action = 'removedependency';
            Templates.renderPix('t/locked', 'core', M.util.get_string('questiondependsonprevious', 'quiz'))
                .then((html) => {
                    icon.innerHTML = html;
                    return true;
                }).catch(Notification.exception);
        } else {
            link.title = M.util.get_string('questiondependencyadd', 'quiz', a);
            link.dataset.action = 'adddependency';
            Templates.renderPix('t/unlocked', 'core', M.util.get_string('questiondependencyfree', 'quiz'))
                .then((html) => {
                    icon.innerHTML = html;
                    return true;
                }).catch(Notification.exception);
        }
    },
};

const page = {
    CSS: {
        PAGE: 'page'
    },
    CONSTANTS: {
        ACTIONMENUIDPREFIX: 'action-menu-',
        ACTIONMENUBARIDSUFFIX: '-menubar',
        ACTIONMENUMENUIDSUFFIX: '-menu',
        PAGEIDPREFIX: 'page-',
        PAGENUMBERPREFIX: getString('page', 'moodle') + ' '
    },
    SELECTORS: {
        ACTIONMENU: 'div.moodle-actionmenu',
        ACTIONMENUBAR: '.menubar',
        ACTIONMENUMENU: '.menu',
        ADDASECTION: '[data-action="addasection"]',
        PAGE: 'li.page',
        INSTANCENAME: '.instancename',
        NUMBER: 'h4'
    },

    /**
     * Retrieve the page item from one of its child Nodes.
     *
     * @param {HTMLElement} pageComponent - The component Node.
     * @return {HTMLElement|null} The Page Node.
     */
    getPageFromComponent: function(pageComponent) {
        return pageComponent.closest(this.SELECTORS.PAGE);
    },

    /**
     * Retrieve the page item from one of its previous siblings.
     *
     * @method getPageFromSlot
     * @param slot {Element} The component Node.
     * @return {Element|null} The Page Node.
     */
    getPageFromSlot: function(slot) {
        let previousElement = slot.previousElementSibling;
        while (previousElement) {
            if (previousElement.matches(this.SELECTORS.PAGE)) {
                return previousElement;
            }
            previousElement = previousElement.previousElementSibling;
        }

        return null;
    },

    /**
     * Returns the page ID for the provided page.
     *
     * @param {HTMLElement} page - The page to find an ID for.
     * @return {Number|false} The ID of the page in question or false if no ID was found.
     */
    getId: function(page) {
        // We perform a simple substitution operation to get the ID.
        let id = page.id.replace(this.CONSTANTS.PAGEIDPREFIX, '');

        // Attempt to validate the ID.
        id = parseInt(id, 10);
        if (!isNaN(id) && isFinite(id)) {
            return id;
        }

        return false;
    },

    /**
     * Updates the page id for the provided page.
     *
     * @param {HTMLElement} page - The page to update the number for.
     * @param {number} id - The id value.
     * @return void
     */
    setId: function(page, id) {
        page.id = `${this.CONSTANTS.PAGEIDPREFIX}${id}`;
    },

    /**
     * Determines the page name for the provided page.
     *
     * @param {HTMLElement} page - The page to find a name for.
     * @return {string|null} The name of the page in question or null if no name was found.
     */
    getName: function(page) {
        const instance = page.querySelector(this.SELECTORS.INSTANCENAME);
        if (instance && instance.firstChild) {
            return instance.firstChild.data ?? instance.firstChild;
        }

        return null;
    },

    /**
     * Determines the page number for the provided page.
     *
     * @param {HTMLElement} page - The page to find a number for.
     * @return {number|false} The number of the page in question or false if no number was found.
     */
     getNumber: function(page) {
        // We perform a simple substitution operation to get the number.
        const numberElement = page.querySelector(this.SELECTORS.NUMBER);
        if (!numberElement) {
            return false;
        }

        let number = numberElement.textContent.replace(this.CONSTANTS.PAGENUMBERPREFIX, '');

        // Attempt to validate the number.
        number = parseInt(number, 10);
        if (!isNaN(number) && isFinite(number)) {
            return number;
        }

        return false;
    },

    /**
     * Updates the page number for the provided page.
     *
     * @param {HTMLElement} page - The page to update the number for.
     * @param {number} number - The number to set for the page.
     * @return void
     */
    setNumber: function(page, number) {
    const numberElement = page.querySelector(this.SELECTORS.NUMBER);
        if (numberElement) {
            numberElement.textContent = this.CONSTANTS.PAGENUMBERPREFIX + number;
        }
    },

    /**
     * Returns a list of all page elements.
     *
     * @return {HTMLElement[]} An array containing page nodes.
     */
    getPages: function() {
        return [...document.querySelectorAll(`${slot.SELECTORS.PAGECONTENT} ${slot.SELECTORS.SECTIONUL} ${this.SELECTORS.PAGE}`)];
    },

    /**
     * Is the given element a page element?
     *
     * @param {HTMLElement} page - Page node
     * @return {boolean}
     */
    isPage: function(page) {
        return page && page.classList.contains(this.CSS.PAGE);
    },

    /**
     * Add a page and related elements to the list of slots.
     *
     * @param {HTMLElement|String} beforeNode - Element to add before.
     * @return {HTMLElement} - Page node
     */
    add: function(beforeNode) {
        const pageFromSlot = this.getPageFromSlot(beforeNode);
        const pageNumber = this.getNumber(pageFromSlot) + 1;
        const pageHtml = config.pagehtml;

        // Normalize the page number.
        const pageHtmlWithNumber = pageHtml.replace(/%%PAGENUMBER%%/g, pageNumber);

        // Create the page node.
        const page = document.createElement('div');
        page.innerHTML = pageHtmlWithNumber;
        const pageNode = page.firstElementChild;

        // Assign it as a drop target.
        // YUI().use('dd-drop', function(Y) {
        //     const drop = new Y.DD.Drop({
        //         node: pageNode,
        //         groups: M.mod_quiz.dragres.groups
        //     });
        //     pageNode.drop = drop;
        // });

        // Insert in the correct place.
        beforeNode.insertAdjacentElement('afterend', pageNode);

        // Enhance the add menu to make if fully visible and clickable.
        if (typeof M.core.actionmenu !== 'undefined') {
            M.core.actionmenu.newDOMNode(pageNode);
        }

        return pageNode;
    },

    /**
     * Does the page have at least one slot?
     *
     * @method isEmpty
     * @param page {Element} Page node
     * @return boolean
     */
    isEmpty: function(page) {
        let activity = page.nextElementSibling;
        while (activity && !activity.matches('li.activity')) {
            activity = activity.nextElementSibling;
        }
        if (!activity) {
            return true;
        }
        return !activity.classList.contains('slot');
    },

    /**
     * Remove a page and related elements from the list of slots.
     *
     * @method remove
     * @param page {Element} Page node.
     * @param keepPageBreak {boolean} Keep page break flag.
     * @return void
     */
    remove: function(page, keepPageBreak) {
        // Remove page break from previous slot.
        let previousSlot = page.previousElementSibling;
        while (previousSlot && !previousSlot.matches(slot.SELECTORS.SLOT)) {
            previousSlot = previousSlot.previousElementSibling;
        }
        if (!keepPageBreak && previousSlot) {
            slot.removePageBreak(previousSlot);
        }
        page.remove();
    },

    /**
     * Reset the order of the numbers given to each page.
     *
     * @return void
     */
    reorderPages: function() {
        const pages = this.getPages();
        let currentPageNumber = 0;
        pages.forEach((page) => {
            if (this.isEmpty(page)) {
                const keepPageBreak = page.nextElementSibling.classList.contains('slot');
                this.remove(page, keepPageBreak);
                return;
            }

            currentPageNumber++;
            this.setNumber(page, currentPageNumber);
            this.setId(page, currentPageNumber);
        });

        this.reorderActionMenus();
    },
    /**
     * Reset the order of the numbers given to each action menu.
     *
     * @return void
     */
    reorderActionMenus: function() {
        const actionMenus = this.getActionMenus();
        actionMenus.forEach((actionMenu, index) => {
            const previousActionMenu = actionMenus[index - 1];
            const previousActionMenuNumber = previousActionMenu ? this.getActionMenuId(previousActionMenu) : 0;
            const id = previousActionMenuNumber + 1;

            this.setActionMenuId(actionMenu, id);

            const menuBar = actionMenu.querySelector(this.SELECTORS.ACTIONMENUBAR);
            menuBar.id = `${this.CONSTANTS.ACTIONMENUIDPREFIX}${id}${this.CONSTANTS.ACTIONMENUBARIDSUFFIX}`;

            const menuMenu = actionMenu.querySelector(this.SELECTORS.ACTIONMENUMENU);
            menuMenu.id = `${this.CONSTANTS.ACTIONMENUIDPREFIX}${id}${this.CONSTANTS.ACTIONMENUMENUIDSUFFIX}`;

            const addSectionLink = menuMenu.querySelector(this.SELECTORS.ADDASECTION);
            addSectionLink.href = addSectionLink.href.replace(/\baddsectionatpage=\d+\b/, `addsectionatpage=${id}`);
        });
    },

    /**
     * Returns a list of all action menu elements.
     *
     * @return {HTMLElement[]} An array containing action menu nodes.
     */
    getActionMenus: function() {
        return Array.from(
            document.querySelectorAll(`${slot.SELECTORS.PAGECONTENT} ${slot.SELECTORS.SECTIONUL} ${this.SELECTORS.ACTIONMENU}`)
        );
    },

    /**
     * Returns the ID for the provided action menu.
     *
     * @param {HTMLElement} actionMenu - The action menu to find an ID for.
     * @return {number|false} The ID of the action menu in question or false if no ID was found.
     */
    getActionMenuId: function(actionMenu) {
        const id = actionMenu.id.replace(this.CONSTANTS.ACTIONMENUIDPREFIX, '');
        const parsedId = parseInt(id, 10);
        return !isNaN(parsedId) && isFinite(parsedId) ? parsedId : false;
    },

    /**
     * Updates the ID for the provided action menu.
     *
     * @param {HTMLElement} actionMenu - The action menu to update the ID for.
     * @param {number} id - The ID value.
     * @return void
     */
    setActionMenuId: function(actionMenu, id) {
        actionMenu.id = `${this.CONSTANTS.ACTIONMENUIDPREFIX}${id}`;
    },
};

const util = {
    /**
     * Appends a hidden spinner element to the specified node.
     *
     * @param {Element} node The node the spinner should be added to
     * @return {Element} created spinner node
     */
    addSpinner: function(node) {
        const WAITICON = {'pix': "i/loading_small", 'component': 'moodle'};

        // Check if spinner is already there
        const currentSpinner = node.querySelector('.spinner');
        if (currentSpinner) {
            return currentSpinner;
        }

        const spinner = this.createElement('img', {
            src: M.util.image_url(WAITICON.pix, WAITICON.component),
            "class": 'spinner iconsmall d-none',
        });
        node.append(spinner);

        return spinner;
    },

    createElement: function(tag, attributes) {
        const element = document.createElement(tag);
        for (let key in attributes) {
            element.setAttribute(key, attributes[key]);
        }

        return element;
    }
};

export {
    slot,
    util,
    page,
};