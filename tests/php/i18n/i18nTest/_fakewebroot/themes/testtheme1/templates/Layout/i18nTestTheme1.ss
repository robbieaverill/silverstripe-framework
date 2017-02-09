<% _t('i18nTestTheme1.LAYOUTTEMPLATE',"Theme1 Layout Template") %>
<% _t('LAYOUTTEMPLATENONAMESPACE',"Theme1 Layout Template no namespace") %>
<%t i18nTestTheme1.PLACEHOLDERNAMESPACE "Theme1 My replacement: {replacement}" replacement=$TestProperty %>
<%t PLACEHOLDERNONAMESPACE "Theme1 My replacement no namespace: {replacement}" replacement=$TestProperty %>
<% include i18nTestTheme1Include %>
