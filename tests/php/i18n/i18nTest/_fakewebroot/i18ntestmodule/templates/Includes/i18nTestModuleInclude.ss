<% _t("i18nTestModule.WITHNAMESPACE", 'Include Entity with Namespace') %>
<% _t("NONAMESPACE", 'Include Entity without Namespace') %>
<%t i18nTestModuleInclude.ss.PLACEHOLDERINCLUDENAMESPACE "My include replacement: {replacement}" replacement=$TestProperty %>
<%t PLACEHOLDERINCLUDENONAMESPACE "My include replacement no namespace: {replacement}" replacement=$TestProperty %>
