pimcore.registerNS("pimcore.plugin.admin.extendPanel");

pimcore.plugin.admin.extendPanel = Class.create({
    
    initialize: function(url, id, name, icon = "pimcore_nav_icon_document") {
        this.name = name;
        this.id = id;
        this.url = url;
        this.icon = icon;
        this.getLayout();
    },

    getLayout: function () {
        if (this.panel == null) {
            // let IFBK$ = null;
            // let IFBKC = null;
            var loadingPanel;

            this.refreshButton = new Ext.Button({
                text: t("Refresh"),
                iconCls: "pimcore_icon_refresh",
               
                handler: function () {
                    try {
                        Ext.get("iframe_manage_panel"+ this.id).dom.src = this.url;
                        loadingPanel = this.panel.setLoading();
                    }
                    catch (e) {
                        console.log(e);
                    }
                }.bind(this)
            });

            this.panel = Ext.create('Ext.panel.Panel', {
                id: this.id,
                title: t(this.name),
                iconCls: this.icon,
                border: false,
                layout: "fit",
                closable: true,
                bodyStyle: "-webkit-overflow-scrolling:touch;",
                tbar: [this.refreshButton],
                listeners: {
                    close: function(btn){
                        localStorage.removeItem(this.id);
                    }
                },
                // html: '<iframe src="about:blank" frameborder="0" width="100%" id="iframe_golf_operating_panel"></iframe>',
                items: [
                    {
                        xtype: 'component',
                        id: 'iframe_manage_panel'+ this.id,
                        autoEl: {
                            tag: 'iframe',
                            style: 'width: 100%; ',
                            src: this.url,
                            frameborder: 0
                        }
                    }
                ],
            });
            this.panel.on("resize", this.onLayoutResize.bind(this));
            var tabPanel = Ext.getCmp("pimcore_panel_tabs");
            tabPanel.add(this.panel);
            tabPanel.setActiveItem(this.panel);
            loadingPanel = this.panel.setLoading();
            pimcore.layout.refresh();

            Ext.get("iframe_manage_panel"+ this.id).on('load', function(){
                loadingPanel.hide();
                // IFBK$ = Ext.get("iframe_manage_panel"+ this.id).dom.contentWindow.$;
                // if(!IFBK$) return;
                // IFBKC = Ext.get("iframe_manage_panel"+ this.id).dom.contentWindow;
               
            }.bind(this));
        }

        return this.panel;

    },

    onLayoutResize: function (el, width, height, rWidth, rHeight) {
        this.setLayoutFrameDimensions(width, height);
    },

    setLayoutFrameDimensions: function (width, height) {
        Ext.get("iframe_manage_panel"+ this.id).setStyle({
            height: (height)  + "px"
        });
    }
});
