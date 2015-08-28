import React from 'react';
import Metabox from './parts/metabox.jsx';


var SelectTemplate = React.createClass({

    selectHandle: function(e) {
        console.log( e.target.getAttribute('data-templateid') );

    },

    render: function () {

        var clickHandle = this.props.onTemplateClick,
            currentTemplate = this.props.template;

        var templatesList = gravityview_view_settings.templates.map( function( template, i ) {

            var classSelected = 'gv-view-types-module';
            classSelected += currentTemplate == template.id ? ' gv-selected' : '';

            var buyOrSelectLink = '',
                previewLink = '',
                linkClass = 'button-primary',
                linkText = '';

            if ( template.buy_source.length ) {
                linkClass += ' button-buy-now';
                linkText = gravityview_i18n.mb_st_buy_button;
                buyOrSelectLink = (
                    <p><a href={template.buy_source} className={linkClass}>{linkText}</a></p>
                );

            } else {
                linkClass += ' button button-large button-select-template';
                linkText = gravityview_i18n.mb_st_select_button;

                buyOrSelectLink = (
                    <p><a data-templateid={template.id} className={linkClass}>{linkText}</a></p>
                );

                if ( template.preview.length ) {
                    previewLink = (
                        <a href={template.preview} rel="external" className="gv-site-preview"><i className="dashicons dashicons-admin-links" title={gravityview_i18n.mb_st_preview}></i></a>
                    );
                }
            }

            return (
                <div key={template.id} className="gv-grid-col-1-3">
                    <div className={classSelected} data-filter={template.type}>
                        <div className="gv-view-types-hover" onClick={clickHandle} >
                            <div>
                                {buyOrSelectLink}
                                {previewLink}
                            </div>
                        </div>
                        <div className="gv-view-types-normal">
                            <img src={template.logo} alt={template.label} />
                            <h5>{template.label}</h5>
                            <p className="description">{template.description}</p>
                        </div>
                    </div>
                </div>
            );

        } );

        return(
            <Metabox mTitle={gravityview_i18n.mb_st_title} mTitleLinks={false}>
                <div className="gv-grid">
                    {templatesList}
                </div>
            </Metabox>
        );
    }
});

export default SelectTemplate;