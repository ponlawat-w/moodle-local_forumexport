require(['jquery'], $ => {
    $(document).ready(() => {
        const $dropdownmenu = $('#page-content #region-main-settings-menu .dropdown .dropdown-menu');
        const itemidentifier = 'local_forumexport_menuitem';

        if (!$dropdownmenu.length) {
            return;
        }

        if ($(`#${itemidentifier}`).length) {
            return;
        }

        const url = M.cfg.wwwroot + '/local/forumexport/export.php?mid=' + LOCAL_FORUMEXPORT_FORUMID;
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
});
