pimcore.registerNS("pimcore.plugin.CorepulseBundle");

pimcore.plugin.CorepulseBundle = Class.create({

    initialize: function () {
        this.isAdmin = pimcore.currentuser.admin;
        this.pimcorePermissions = pimcore.currentuser.permissions;
        this.activePerspective = pimcore.currentuser.activePerspective;
        
        if (this.isAdmin) {
            document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
        }
    },

    pimcoreReady: function (e) {
        var toolbar = pimcore.globalmanager.get("layout_toolbar");

        if (Ext.get('pimcore_menu_settings')) {
            this.settingButton = Ext.get('pimcore_menu_settings').insertSibling('<li id="pimcore_menu_settingButton"\
                data-menu-tooltip="Corepulse CMS"\
                class="pimcore_menu_item pimcore_menu_needs_children corepulse-icon">\
                <img src="/bundles/corepulse/image/corepulse.png"></li>\
                ', 'after');

            this.settingButton.on("mousedown", function() {
                var sitemapSetting = Ext.get("corepulseBundle");
                if (sitemapSetting) {
                    var tabPanel = Ext.getCmp("pimcore_panel_tabs");
                    tabPanel.setActiveItem("corepulseBundle");
                } else {
                    new pimcore.plugin.admin.extendPanel('/admin/vuetify/setting/object','corepulseBundle','Corepulse CMS Setting', 'corepulse-cms');
                    localStorage.setItem('corepulseBundle', 'corepulseBundle');
                }
            });

            pimcore.helpers.initMenuTooltips();
        }
    }
});

var CorepulseBundlePlugin = new pimcore.plugin.CorepulseBundle();