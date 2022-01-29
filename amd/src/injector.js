import $ from 'jquery';

export const init = url => {
    $(() => {
        const $dropdownmenu = $('#page-content #region-main-settings-menu .dropdown .dropdown-menu');
        const itemidentifier = 'local_forumexport_menuitem';

        if (!$dropdownmenu.length) {
            return;
        }

        if ($(`#${itemidentifier}`).length) {
            return;
        }

        const text = M.str.local_forumexport.export_extendedfunctionalities;

        $('<div>')
            .addClass('dropdown-item')
            .html(
                $('<a>')
                    .attr('href', url)
                    .attr('role', 'menuitem')
                    .attr('id', itemidentifier)
                    .html(text)
            ).appendTo($dropdownmenu);
    });
};
