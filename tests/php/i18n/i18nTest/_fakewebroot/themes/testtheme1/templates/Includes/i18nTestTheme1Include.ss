<% _t("i18nTestTheme1Include.WITHNAMESPACE", 'Theme1 Include Entity with Namespace') %>
<% _t("NONAMESPACE", 'Theme1 Include Entity without Namespace') %>
<%t i18nTestTheme1Include.PLACEHOLDERINCLUDENAMESPACE "Theme1 My include replacement: {replacement}" replacement=$TestProperty %>
<%t PLACEHOLDERINCLUDENONAMESPACE "Theme1 My include replacement no namespace: {replacement}" replacement=$TestProperty %>
