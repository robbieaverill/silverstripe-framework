<?php

namespace SilverStripe\i18n;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Dev\Deprecation;
use SilverStripe\i18n\Messages\MessageProvider;
use SilverStripe\View\SSViewer;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\View\ThemeResourceLoader;
use InvalidArgumentException;

/**
 * Base-class for storage and retrieval of translated entities.
 *
 * Please see the 'translatable' module for managing translations of database-content.
 *
 * <b>Usage</b>
 *
 * PHP:
 * <code>
 * _t('MyNamespace.MYENTITY', 'My default natural language value');
 * _t('MyNamespace.MYENTITY', 'My default natural language value', 'My explanatory context');
 * _t('MyNamespace.MYENTITY', 'Counting {number} things', ['number' => 42]);
 * </code>
 *
 * Templates:
 * <code>
 * <%t MyNamespace.MYENTITY 'My default natural language value' %>
 * <%t MyNamespace.MYENTITY 'Counting {count} things' count=$ThingsCount %>
 * </code>
 *
 * Javascript (see framework/client/dist/js/i18n.js):
 * <code>
 * ss.i18n._t('MyEntity.MyNamespace','My default natural language value');
 * </code>
 *
 * File-based i18n-translations always have a "locale" (e.g. 'en_US').
 * Common language names (e.g. 'en') are mainly used in the 'translatable' module
 * database-entities.
 *
 * <b>Text Collection</b>
 *
 * Features a "textcollector-mode" that parses all files with a certain extension
 * (currently *.php and *.ss) for new translatable strings. Textcollector will write
 * updated string-tables to their respective folders inside the module, and automatically
 * namespace entities to the classes/templates they are found in (e.g. $lang['en_US']['AssetAdmin']['UPLOADFILES']).
 *
 * Caution: Does not apply any character-set conversion, it is assumed that all content
 * is stored and represented in UTF-8 (Unicode). Please make sure your files are created with the correct
 * character-set, and your HTML-templates render UTF-8.
 *
 * Caution: The language file has to be stored in the same module path as the "filename namespaces"
 * on the entities. So an entity stored in $lang['en_US']['AssetAdmin']['DETAILSTAB'] has to
 * in the language file cms/lang/en_US.php, as the referenced file (AssetAdmin.php) is stored
 * in the "cms" module.
 *
 * <b>Locales</b>
 *
 * For the i18n class, a "locale" consists of a language code plus a region code separated by an underscore,
 * for example "de_AT" for German language ("de") in the region Austria ("AT").
 * See http://www.w3.org/International/articles/language-tags/ for a detailed description.
 *
 * @see http://doc.silverstripe.org/i18n
 * @see http://www.w3.org/TR/i18n-html-tech-lang
 *
 * @author Bernat Foj Capell <bernat@silverstripe.com>
 */
class i18n implements TemplateGlobalProvider
{
    use Injectable;
    use Configurable;

    /**
     * This static variable is used to store the current defined locale.
     *
     * @var string
     */
    protected static $current_locale = '';

    /**
     * @config
     * @var string
     */
    private static $default_locale = 'en_US';

    /**
     * @config
     * @var string
     */
    private static $date_format = 'yyyy-MM-dd';

    /**
     * @config
     * @var string
     */
    private static $time_format = 'H:mm';

    /**
     * List of prioritised modules, in lowest to highest priority.
     *
     * @config
     * @var array
     */
    private static $module_priority = [];

    /**
     * Config for ltr/rtr of specific locales.
     * Will default to ltr.
     *
     * @config
     * @var array
     */
    private static $text_direction = [
        'ar' => 'rtl',
        'dv' => 'rtl',
        'fa' => 'rtl',
        'ha_Arab' => 'rtl',
        'he' => 'rtl',
        'ku' => 'rtl',
        'pa_Arab' => 'rtl',
        'ps' => 'rtl',
        'syr' => 'rtl',
        'ug' => 'rtl',
        'ur' => 'rtl',
        'uz_Arab' => 'rtl',
    ];

    /**
     * An exhaustive list of possible locales (code => language and country)
     *
     * @config
     * @var array
     */
    private static $all_locales = array (
        'aa_DJ' => 'Afar (Djibouti)',
        'ab_GE' => 'Abkhazian (Georgia)',
        'abr_GH' => 'Abron (Ghana)',
        'ace_ID' => 'Achinese (Indonesia)',
        'ady_RU' => 'Adyghe (Russia)',
        'af_ZA' => 'Afrikaans (South Africa)',
        'ak_GH' => 'Akan (Ghana)',
        'am_ET' => 'Amharic (Ethiopia)',
        'ar_AE' => 'Arabic (United Arab Emirates)',
        'ar_BH' => 'Arabic (Bahrain)',
        'ar_DZ' => 'Arabic (Algeria)',
        'ar_EG' => 'Arabic (Egypt)',
        'ar_EH' => 'Arabic (Western Sahara)',
        'ar_IQ' => 'Arabic (Iraq)',
        'ar_JO' => 'Arabic (Jordan)',
        'ar_KW' => 'Arabic (Kuwait)',
        'ar_LB' => 'Arabic (Lebanon)',
        'ar_LY' => 'Arabic (Libya)',
        'ar_MA' => 'Arabic (Morocco)',
        'ar_MR' => 'Arabic (Mauritania)',
        'ar_OM' => 'Arabic (Oman)',
        'ar_PS' => 'Arabic (Palestinian Territory)',
        'ar_QA' => 'Arabic (Qatar)',
        'ar_SA' => 'Arabic (Saudi Arabia)',
        'ar_SD' => 'Arabic (Sudan)',
        'ar_SY' => 'Arabic (Syria)',
        'ar_TD' => 'Arabic (Chad)',
        'ar_TN' => 'Arabic (Tunisia)',
        'ar_YE' => 'Arabic (Yemen)',
        'as_IN' => 'Assamese (India)',
        'ast_ES' => 'Asturian (Spain)',
        'auv_FR' => 'Auvergnat (France)',
        'av_RU' => 'Avaric (Russia)',
        'awa_IN' => 'Awadhi (India)',
        'ay_BO' => 'Aymara (Bolivia)',
        'ay_PE' => 'Aymara (Peru)',
        'az_AZ' => 'Azerbaijani (Azerbaijan)',
        'az_IR' => 'Azerbaijani (Iran)',
        'ba_RU' => 'Bashkir (Russia)',
        'ban_ID' => 'Balinese (Indonesia)',
        'bcc_PK' => 'Balochi, Southern (Pakistan)',
        'bcl_PH' => 'Bicolano, Central (Philippines)',
        'be_BY' => 'Belarusian (Belarus)',
        'bew_ID' => 'Betawi (Indonesia)',
        'bg_BG' => 'Bulgarian (Bulgaria)',
        'bgc_IN' => 'Haryanvi (India)',
        'bgn_PK' => 'Balochi, Western (Pakistan)',
        'bgp_PK' => 'Balochi, Easter (Pakistan)',
        'bhb_IN' => 'Bhili (India)',
        'bhi_IN' => 'Bhilali (India)',
        'bhk_PH' => 'Bicolano, Albay (Philippines)',
        'bho_IN' => 'Bhojpuri (India)',
        'bho_MU' => 'Bhojpuri (Mauritius)',
        'bho_NP' => 'Bhojpuri (Nepal)',
        'bi_VU' => 'Bislama (Vanuatu)',
        'bjj_IN' => 'Kanauji (India)',
        'bjn_ID' => 'Banjar (Indonesia)',
        'bm_ML' => 'Bambara (Mali)',
        'bn_BD' => 'Bengali (Bangladesh)',
        'bn_IN' => 'Bengali (India)',
        'bo_CN' => 'Tibetan (China)',
        'bqi_IR' => 'Bakhtiari (Iran)',
        'brh_PK' => 'Brahui (Pakistan)',
        'bs_BA' => 'Bosnian (Bosnia and Herzegovina)',
        'btk_ID' => 'Batak (Indonesia)',
        'buc_YT' => 'Bushi (Mayotte)',
        'bug_ID' => 'Buginese (Indonesia)',
        'ca_AD' => 'Catalan (Andorra)',
        'ca_ES' => 'Catalan (Spain)',
        'ce_RU' => 'Chechen (Russia)',
        'ceb_PH' => 'Cebuano (Philippines)',
        'cgg_UG' => 'Chiga (Uganda)',
        'ch_GU' => 'Chamorro (Guam)',
        'chk_FM' => 'Chuukese (Micronesia)',
        'crk_CA' => 'Cree, Plains (Canada)',
        'cs_CZ' => 'Czech (Czech Republic)',
        'cwd_CA' => 'Cree, Woods (Canada)',
        'cy_GB' => 'Welsh (United Kingdom)',
        'da_DK' => 'Danish (Denmark)',
        'da_GL' => 'Danish (Greenland)',
        'dcc_IN' => 'Deccan (India)',
        'de_AT' => 'German (Austria)',
        'de_BE' => 'German (Belgium)',
        'de_CH' => 'German (Switzerland)',
        'de_DE' => 'German (Germany)',
        'de_LI' => 'German (Liechtenstein)',
        'de_LU' => 'German (Luxembourg)',
        'dgo_IN' => 'Dogri (India)',
        'dhd_IN' => 'Dhundari (India)',
        'diq_TR' => 'Dimli (Turkey)',
        'dje_NE' => 'Zarma (Niger)',
        'dv_MV' => 'Divehi (Maldives)',
        'dz_BT' => 'Dzongkha (Bhutan)',
        'ee_GH' => 'Ewe (Ghana)',
        'el_CY' => 'Greek (Cyprus)',
        'el_GR' => 'Greek (Greece)',
        'en_AS' => 'English (American Samoa)',
        'en_AU' => 'English (Australia)',
        'en_BM' => 'English (Bermuda)',
        'en_BS' => 'English (Bahamas)',
        'en_CA' => 'English (Canada)',
        'en_DE' => 'English (Germany)',
        'en_ES' => 'English (Spain)',
        'en_FR' => 'English (France)',
        'en_GB' => 'English (United Kingdom)',
        'en_HK' => 'English (Hong Kong SAR China)',
        'en_IE' => 'English (Ireland)',
        'en_IN' => 'English (India)',
        'en_IT' => 'English (Italy)',
        'en_JM' => 'English (Jamaica)',
        'en_KE' => 'English (Kenya)',
        'en_LR' => 'English (Liberia)',
        'en_MM' => 'English (Myanmar)',
        'en_MW' => 'English (Malawi)',
        'en_MY' => 'English (Malaysia)',
        'en_NL' => 'English (Netherlands)',
        'en_NZ' => 'English (New Zealand)',
        'en_PH' => 'English (Philippines)',
        'en_SG' => 'English (Singapore)',
        'en_TT' => 'English (Trinidad and Tobago)',
        'en_US' => 'English (United States)',
        'en_ZA' => 'English (South Africa)',
        'eo_XX' => 'Esperanto',
        'es_419' => 'Spanish (Latin America)',
        'es_AR' => 'Spanish (Argentina)',
        'es_BO' => 'Spanish (Bolivia)',
        'es_CL' => 'Spanish (Chile)',
        'es_CO' => 'Spanish (Colombia)',
        'es_CR' => 'Spanish (Costa Rica)',
        'es_CU' => 'Spanish (Cuba)',
        'es_DO' => 'Spanish (Dominican Republic)',
        'es_EC' => 'Spanish (Ecuador)',
        'es_ES' => 'Spanish (Spain)',
        'es_GQ' => 'Spanish (Equatorial Guinea)',
        'es_GT' => 'Spanish (Guatemala)',
        'es_HN' => 'Spanish (Honduras)',
        'es_MX' => 'Spanish (Mexico)',
        'es_NI' => 'Spanish (Nicaragua)',
        'es_PA' => 'Spanish (Panama)',
        'es_PE' => 'Spanish (Peru)',
        'es_PH' => 'Spanish (Philippines)',
        'es_PR' => 'Spanish (Puerto Rico)',
        'es_PY' => 'Spanish (Paraguay)',
        'es_SV' => 'Spanish (El Salvador)',
        'es_US' => 'Spanish (United States)',
        'es_UY' => 'Spanish (Uruguay)',
        'es_VE' => 'Spanish (Venezuela)',
        'et_EE' => 'Estonian (Estonia)',
        'eu_ES' => 'Basque (Spain)',
        'fa_AF' => 'Persian (Afghanistan)',
        'fa_IR' => 'Persian (Iran)',
        'fa_PK' => 'Persian (Pakistan)',
        'fan_GQ' => 'Fang (Equatorial Guinea)',
        'fi_FI' => 'Finnish (Finland)',
        'fi_SE' => 'Finnish (Sweden)',
        'fil_PH' => 'Filipino (Philippines)',
        'fj_FJ' => 'Fijian (Fiji)',
        'fo_FO' => 'Faroese (Faroe Islands)',
        'fon_BJ' => 'Fon (Benin)',
        'fr_002' => 'French (Africa)',
        'fr_BE' => 'French (Belgium)',
        'fr_CA' => 'French (Canada)',
        'fr_CH' => 'French (Switzerland)',
        'fr_DZ' => 'French (Algeria)',
        'fr_FR' => 'French (France)',
        'fr_GF' => 'French (French Guiana)',
        'fr_GP' => 'French (Guadeloupe)',
        'fr_HT' => 'French (Haiti)',
        'fr_KM' => 'French (Comoros)',
        'fr_MA' => 'French (Morocco)',
        'fr_MQ' => 'French (Martinique)',
        'fr_MU' => 'French (Mauritius)',
        'fr_NC' => 'French (New Caledonia)',
        'fr_PF' => 'French (French Polynesia)',
        'fr_PM' => 'French (Saint Pierre and Miquelon)',
        'fr_RE' => 'French (Reunion)',
        'fr_SC' => 'French (Seychelles)',
        'fr_SN' => 'French (Senegal)',
        'fr_US' => 'French (United States)',
        'fuv_NG' => 'Fulfulde (Nigeria)',
        'ga_GB' => 'Irish (United Kingdom)',
        'ga_IE' => 'Irish (Ireland)',
        'gaa_GH' => 'Ga (Ghana)',
        'gbm_IN' => 'Garhwali (India)',
        'gcr_GF' => 'Guianese Creole French (French Guiana)',
        'gd_GB' => 'Scottish Gaelic (United Kingdom)',
        'gil_KI' => 'Gilbertese (Kiribati)',
        'gl_ES' => 'Galician (Spain)',
        'glk_IR' => 'Gilaki (Iran)',
        'gn_PY' => 'Guarani (Paraguay)',
        'gno_IN' => 'Gondi, Northern (India)',
        'gsw_CH' => 'Swiss German (Switzerland)',
        'gsw_LI' => 'Swiss German (Liechtenstein)',
        'gu_IN' => 'Gujarati (India)',
        'guz_KE' => 'Gusii (Kenya)',
        'ha_NE' => 'Hausa (Niger)',
        'ha_NG' => 'Hausa (Nigeria)',
        'haw_US' => 'Hawaiian (United States)',
        'haz_AF' => 'Hazaragi (Afghanistan)',
        'he_IL' => 'Hebrew (Israel)',
        'hi_IN' => 'Hindi (India)',
        'hil_PH' => 'Hiligaynon (Philippines)',
        'hne_IN' => 'Chhattisgarhi (India)',
        'hno_PK' => 'Hindko, Northern (Pakistan)',
        'hoc_IN' => 'Ho (India)',
        'hr_AT' => 'Croatian (Austria)',
        'hr_BA' => 'Croatian (Bosnia and Herzegovina)',
        'hr_HR' => 'Croatian (Croatia)',
        'ht_HT' => 'Haitian (Haiti)',
        'hu_AT' => 'Hungarian (Austria)',
        'hu_HU' => 'Hungarian (Hungary)',
        'hu_RO' => 'Hungarian (Romania)',
        'hu_RS' => 'Hungarian (Serbia)',
        'hy_AM' => 'Armenian (Armenia)',
        'id_ID' => 'Indonesian (Indonesia)',
        'ig_NG' => 'Igbo (Nigeria)',
        'ilo_PH' => 'Iloko (Philippines)',
        'inh_RU' => 'Ingush (Russia)',
        'is_IS' => 'Icelandic (Iceland)',
        'it_CH' => 'Italian (Switzerland)',
        'it_FR' => 'Italian (France)',
        'it_HR' => 'Italian (Croatia)',
        'it_IT' => 'Italian (Italy)',
        'it_SM' => 'Italian (San Marino)',
        'it_US' => 'Italian (United States)',
        'iu_CA' => 'Inuktitut (Canada)',
        'ja_JP' => 'Japanese (Japan)',
        'jv_ID' => 'Javanese (Indonesia)',
        'ka_GE' => 'Georgian (Georgia)',
        'kam_KE' => 'Kamba (Kenya)',
        'kbd_RU' => 'Kabardian (Russia)',
        'kfy_IN' => 'Kumauni (India)',
        'kha_IN' => 'Khasi (India)',
        'khn_IN' => 'Khandesi (India)',
        'ki_KE' => 'Kikuyu (Kenya)',
        'kj_NA' => 'Kuanyama (Namibia)',
        'kk_CN' => 'Kazakh (China)',
        'kk_KZ' => 'Kazakh (Kazakhstan)',
        'kl_DK' => 'Kalaallisut (Denmark)',
        'kl_GL' => 'Kalaallisut (Greenland)',
        'kln_KE' => 'Kalenjin (Kenya)',
        'km_KH' => 'Khmer (Cambodia)',
        'kn_IN' => 'Kannada (India)',
        'ko_KR' => 'Korean (Korea)',
        'koi_RU' => 'Komi-Permyak (Russia)',
        'kok_IN' => 'Konkani (India)',
        'kos_FM' => 'Kosraean (Micronesia)',
        'kpv_RU' => 'Komi-Zyrian (Russia)',
        'krc_RU' => 'Karachay-Balkar (Russia)',
        'kru_IN' => 'Kurukh (India)',
        'ks_IN' => 'Kashmiri (India)',
        'ku_IQ' => 'Kurdish (Iraq)',
        'ku_IR' => 'Kurdish (Iran)',
        'ku_SY' => 'Kurdish (Syria)',
        'ku_TR' => 'Kurdish (Turkey)',
        'kum_RU' => 'Kumyk (Russia)',
        'kxm_TH' => 'Khmer, Northern (Thailand)',
        'ky_KG' => 'Kirghiz (Kyrgyzstan)',
        'la_VA' => 'Latin (Vatican)',
        'lah_PK' => 'Lahnda (Pakistan)',
        'lb_LU' => 'Luxembourgish (Luxembourg)',
        'lbe_RU' => 'Lak (Russia)',
        'lc_XX' => 'LOLCAT',
        'lez_RU' => 'Lezghian (Russia)',
        'lg_UG' => 'Ganda (Uganda)',
        'lij_IT' => 'Ligurian (Italy)',
        'lij_MC' => 'Ligurian (Monaco)',
        'ljp_ID' => 'Lampung (Indonesia)',
        'lmn_IN' => 'Lambadi (India)',
        'ln_CD' => 'Lingala (Congo - Kinshasa)',
        'ln_CG' => 'Lingala (Congo - Brazzaville)',
        'lo_LA' => 'Lao (Laos)',
        'lrc_IR' => 'Luri, Northern (Iran)',
        'lt_LT' => 'Lithuanian (Lithuania)',
        'luo_KE' => 'Luo (Kenya)',
        'luy_KE' => 'Luyia (Kenya)',
        'lv_LV' => 'Latvian (Latvia)',
        'mad_ID' => 'Madurese (Indonesia)',
        'mai_IN' => 'Maithili (India)',
        'mai_NP' => 'Maithili (Nepal)',
        'mak_ID' => 'Makasar (Indonesia)',
        'mdf_RU' => 'Moksha (Russia)',
        'mdh_PH' => 'Maguindanao (Philippines)',
        'mer_KE' => 'Meru (Kenya)',
        'mfa_TH' => 'Malay, Pattani (Thailand)',
        'mfe_MU' => 'Morisyen (Mauritius)',
        'mg_MG' => 'Malagasy (Madagascar)',
        'mh_MH' => 'Marshallese (Marshall Islands)',
        'mi_NZ' => 'te reo Māori (New Zealand)',
        'min_ID' => 'Minangkabau (Indonesia)',
        'mk_MK' => 'Macedonian (Macedonia)',
        'ml_IN' => 'Malayalam (India)',
        'mn_CN' => 'Mongolian (China)',
        'mn_MN' => 'Mongolian (Mongolia)',
        'mni_IN' => 'Manipuri (India)',
        'mr_IN' => 'Marathi (India)',
        'ms_BN' => 'Malay (Brunei)',
        'ms_CC' => 'Malay (Cocos Islands)',
        'ms_ID' => 'Malay (Indonesia)',
        'ms_MY' => 'Malay (Malaysia)',
        'ms_SG' => 'Malay (Singapore)',
        'mt_MT' => 'Maltese (Malta)',
        'mtr_IN' => 'Mewari (India)',
        'mup_IN' => 'Malvi (India)',
        'muw_IN' => 'Mundari (India)',
        'my_MM' => 'Burmese (Myanmar)',
        'myv_RU' => 'Erzya (Russia)',
        'na_NR' => 'Nauru (Nauru)',
        'nb_NO' => 'Norwegian Bokmal (Norway)',
        'nb_SJ' => 'Norwegian Bokmal (Svalbard and Jan Mayen)',
        'nd_ZW' => 'North Ndebele (Zimbabwe)',
        'ndc_MZ' => 'Ndau (Mozambique)',
        'ne_IN' => 'Nepali (India)',
        'ne_NP' => 'Nepali (Nepal)',
        'ng_NA' => 'Ndonga (Namibia)',
        'ngl_MZ' => 'Lomwe (Mozambique)',
        'niu_NU' => 'Niuean (Niue)',
        'nl_AN' => 'Dutch (Netherlands Antilles)',
        'nl_AW' => 'Dutch (Aruba)',
        'nl_BE' => 'Dutch (Belgium)',
        'nl_NL' => 'Dutch (Netherlands)',
        'nl_SR' => 'Dutch (Suriname)',
        'nn_NO' => 'Norwegian Nynorsk (Norway)',
        'nod_TH' => 'Thai, Northern (Thailand)',
        'noe_IN' => 'Nimadi (India)',
        'nso_ZA' => 'Northern Sotho (South Africa)',
        'ny_MW' => 'Nyanja (Malawi)',
        'ny_ZM' => 'Nyanja (Zambia)',
        'nyn_UG' => 'Nyankole (Uganda)',
        'om_ET' => 'Oromo (Ethiopia)',
        'or_IN' => 'Oriya (India)',
        'pa_IN' => 'Punjabi (India)',
        'pag_PH' => 'Pangasinan (Philippines)',
        'pap_AN' => 'Papiamento (Netherlands Antilles)',
        'pap_AW' => 'Papiamento (Aruba)',
        'pau_PW' => 'Palauan (Palau)',
        'pl_PL' => 'Polish (Poland)',
        'pl_UA' => 'Polish (Ukraine)',
        'pon_FM' => 'Pohnpeian (Micronesia)',
        'ps_AF' => 'Pashto (Afghanistan)',
        'ps_PK' => 'Pashto (Pakistan)',
        'pt_AO' => 'Portuguese (Angola)',
        'pt_BR' => 'Portuguese (Brazil)',
        'pt_CV' => 'Portuguese (Cape Verde)',
        'pt_GW' => 'Portuguese (Guinea-Bissau)',
        'pt_MZ' => 'Portuguese (Mozambique)',
        'pt_PT' => 'Portuguese (Portugal)',
        'pt_ST' => 'Portuguese (Sao Tome and Principe)',
        'pt_TL' => 'Portuguese (East Timor)',
        'qu_BO' => 'Quechua (Bolivia)',
        'qu_PE' => 'Quechua (Peru)',
        'rcf_RE' => 'R�union Creole French (Reunion)',
        'rej_ID' => 'Rejang (Indonesia)',
        'rif_MA' => 'Tarifit (Morocco)',
        'rjb_IN' => 'Rajbanshi (India)',
        'rm_CH' => 'Rhaeto-Romance (Switzerland)',
        'rmt_IR' => 'Domari (Iran)',
        'rn_BI' => 'Rundi (Burundi)',
        'ro_MD' => 'Romanian (Moldova)',
        'ro_RO' => 'Romanian (Romania)',
        'ro_RS' => 'Romanian (Serbia)',
        'ru_BY' => 'Russian (Belarus)',
        'ru_KG' => 'Russian (Kyrgyzstan)',
        'ru_KZ' => 'Russian (Kazakhstan)',
        'ru_RU' => 'Russian (Russia)',
        'ru_SJ' => 'Russian (Svalbard and Jan Mayen)',
        'ru_UA' => 'Russian (Ukraine)',
        'rw_RW' => 'Kinyarwanda (Rwanda)',
        'sa_IN' => 'Sanskrit (India)',
        'sah_RU' => 'Yakut (Russia)',
        'sas_ID' => 'Sasak (Indonesia)',
        'sat_IN' => 'Santali (India)',
        'sck_IN' => 'Sadri (India)',
        'sco_GB' => 'Scots (United Kingdom)',
        'sco_SCO' => 'Scots',
        'sd_IN' => 'Sindhi (India)',
        'sd_PK' => 'Sindhi (Pakistan)',
        'se_NO' => 'Northern Sami (Norway)',
        'sg_CF' => 'Sango (Central African Republic)',
        'si_LK' => 'Sinhalese (Sri Lanka)',
        'sid_ET' => 'Sidamo (Ethiopia)',
        'sk_RS' => 'Slovak (Serbia)',
        'sk_SK' => 'Slovak (Slovakia)',
        'sl_AT' => 'Slovenian (Austria)',
        'sl_SI' => 'Slovenian (Slovenia)',
        'sm_AS' => 'Samoan (American Samoa)',
        'sm_WS' => 'Samoan (Samoa)',
        'sn_ZW' => 'Shona (Zimbabwe)',
        'so_DJ' => 'Somali (Djibouti)',
        'so_ET' => 'Somali (Ethiopia)',
        'so_SO' => 'Somali (Somalia)',
        'sou_TH' => 'Thai, Southern (Thailand)',
        'sq_AL' => 'Albanian (Albania)',
        'sr_BA' => 'Serbian (Bosnia and Herzegovina)',
        'sr_ME' => 'Serbian (Montenegro)',
        'sr_RS' => 'Serbian (Serbia)',
        'ss_SZ' => 'Swati (Swaziland)',
        'ss_ZA' => 'Swati (South Africa)',
        'st_LS' => 'Southern Sotho (Lesotho)',
        'st_ZA' => 'Southern Sotho (South Africa)',
        'su_ID' => 'Sundanese (Indonesia)',
        'sv_AX' => 'Swedish (Aland Islands)',
        'sv_FI' => 'Swedish (Finland)',
        'sv_SE' => 'Swedish (Sweden)',
        'sw_KE' => 'Swahili (Kenya)',
        'sw_SO' => 'Swahili (Somalia)',
        'sw_TZ' => 'Swahili (Tanzania)',
        'sw_UG' => 'Swahili (Uganda)',
        'swb_KM' => 'Comorian (Comoros)',
        'swb_YT' => 'Comorian (Mayotte)',
        'swv_IN' => 'Shekhawati (India)',
        'ta_IN' => 'Tamil (India)',
        'ta_LK' => 'Tamil (Sri Lanka)',
        'ta_MY' => 'Tamil (Malaysia)',
        'ta_SG' => 'Tamil (Singapore)',
        'tcy_IN' => 'Tulu (India)',
        'te_IN' => 'Telugu (India)',
        'tet_TL' => 'Tetum (East Timor)',
        'tg_TJ' => 'Tajik (Tajikistan)',
        'th_TH' => 'Thai (Thailand)',
        'ti_ER' => 'Tigrinya (Eritrea)',
        'ti_ET' => 'Tigrinya (Ethiopia)',
        'tk_IR' => 'Turkmen (Iran)',
        'tk_TM' => 'Turkmen (Turkmenistan)',
        'tkl_TK' => 'Tokelau (Tokelau)',
        'tl_PH' => 'Tagalog (Philippines)',
        'tl_US' => 'Tagalog (United States)',
        'tn_BW' => 'Tswana (Botswana)',
        'tn_ZA' => 'Tswana (South Africa)',
        'to_TO' => 'Tonga (Tonga)',
        'tr_CY' => 'Turkish (Cyprus)',
        'tr_DE' => 'Turkish (Germany)',
        'tr_MK' => 'Turkish (Macedonia)',
        'tr_TR' => 'Turkish (Turkey)',
        'ts_MZ' => 'Tsonga (Mozambique)',
        'ts_ZA' => 'Tsonga (South Africa)',
        'tsg_PH' => 'Tausug (Philippines)',
        'tt_RU' => 'Tatar (Russia)',
        'tts_TH' => 'Thai, Northeastern (Thailand)',
        'tvl_TV' => 'Tuvalu (Tuvalu)',
        'tw_GH' => 'Twi (Ghana)',
        'ty_PF' => 'Tahitian (French Polynesia)',
        'tyv_RU' => 'Tuvinian (Russia)',
        'tzm_MA' => 'Tamazight, Central Atlas (Morocco)',
        'udm_RU' => 'Udmurt (Russia)',
        'ug_CN' => 'Uighur (China)',
        'uk_UA' => 'Ukrainian (Ukraine)',
        'uli_FM' => 'Ulithian (Micronesia)',
        'ur_IN' => 'Urdu (India)',
        'ur_PK' => 'Urdu (Pakistan)',
        'uz_AF' => 'Uzbek (Afghanistan)',
        'uz_UZ' => 'Uzbek (Uzbekistan)',
        've_ZA' => 'Venda (South Africa)',
        'vi_US' => 'Vietnamese (United States)',
        'vi_VN' => 'Vietnamese (Vietnam)',
        'vmw_MZ' => 'Waddar (Mozambique)',
        'wal_ET' => 'Walamo (Ethiopia)',
        'war_PH' => 'Waray (Philippines)',
        'wbq_IN' => 'Waddar (India)',
        'wbr_IN' => 'Wagdi (India)',
        'wo_MR' => 'Wolof (Mauritania)',
        'wo_SN' => 'Wolof (Senegal)',
        'wtm_IN' => 'Mewati (India)',
        'xh_ZA' => 'Xhosa (South Africa)',
        'xnr_IN' => 'Kangri (India)',
        'xog_UG' => 'Soga (Uganda)',
        'yap_FM' => 'Yapese (Micronesia)',
        'yo_NG' => 'Yoruba (Nigeria)',
        'za_CN' => 'Zhuang (China)',
        'zh_CN' => 'Chinese (China)',
        'zh_HK' => 'Chinese (Hong Kong SAR China)',
        'zh_MO' => 'Chinese (Macao SAR China)',
        'zh_SG' => 'Chinese (Singapore)',
        'zh_TW' => 'Chinese (Taiwan)',
        'zh_US' => 'Chinese (United States)',
        'zh_cmn' => 'Chinese (Mandarin)',
        'zh_yue' => 'Chinese (Cantonese)',
        'zu_ZA' => 'Zulu (South Africa)'
    );

    /**
     * @config
     * @var array $common_languages A list of commonly used languages, in the form
     * langcode => array( EnglishName, NativeName)
     */
    private static $common_languages = array(
        'af' => array(
            'name' => 'Afrikaans',
            'native' => 'Afrikaans'
        ),
        'sq' => array(
            'name' => 'Albanian',
            'native' => 'shqip'
        ),
        'ar' => array(
            'name' => 'Arabic',
            'native' => '&#1575;&#1604;&#1593;&#1585;&#1576;&#1610;&#1577;'
        ),
        'eu' => array(
            'name' => 'Basque',
            'native' => 'euskera'
        ),
        'be' => array(
            'name' => 'Belarusian',
            'native' =>
                '&#1041;&#1077;&#1083;&#1072;&#1088;&#1091;&#1089;&#1082;&#1072;&#1103; &#1084;&#1086;&#1074;&#1072;'
        ),
        'bn' => array(
            'name' => 'Bengali',
            'native' => '&#2476;&#2494;&#2434;&#2482;&#2494;'
        ),
        'bg' => array(
            'name' => 'Bulgarian',
            'native' => '&#1073;&#1098;&#1083;&#1075;&#1072;&#1088;&#1089;&#1082;&#1080;'
        ),
        'ca' => array(
            'name' => 'Catalan',
            'native' => 'catal&agrave;'
        ),
        'zh_yue' => array(
            'name' => 'Chinese (Cantonese)',
            'native' => '&#24291;&#26481;&#35441; [&#24191;&#19996;&#35805;]'
        ),
        'zh_cmn' => array(
            'name' => 'Chinese (Mandarin)',
            'native' => '&#26222;&#36890;&#35441; [&#26222;&#36890;&#35805;]'
        ),
        'hr' => array(
            'name' => 'Croatian',
            'native' => 'Hrvatski'
        ),
        'zh' => array(
            'name' => 'Chinese',
            'native' => '&#20013;&#25991;'
        ),
        'cs' => array(
            'name' => 'Czech',
            'native' => '&#x010D;e&#353;tina'
        ),
        'cy' => array(
            'name' => 'Welsh',
            'native' => 'Welsh/Cymraeg'
        ),
        'da' => array(
            'name' => 'Danish',
            'native' => 'dansk'
        ),
        'nl' => array(
            'name' => 'Dutch',
            'native' => 'Nederlands'
        ),
        'en' => array(
            'name' => 'English',
            'native' => 'English'
        ),
        'eo' => array(
            'name' => 'Esperanto',
            'native' => 'Esperanto'
        ),
        'et' => array(
            'name' => 'Estonian',
            'native' => 'eesti keel'
        ),
        'fo' => array(
            'name' => 'Faroese',
            'native' => 'F&oslash;royska'
        ),
        'fi' => array(
            'name' => 'Finnish',
            'native' => 'suomi'
        ),
        'fr' => array(
            'name' => 'French',
            'native' => 'fran&ccedil;ais'
        ),
        'gd' => array(
            'name' => 'Gaelic',
            'native' => 'Gaeilge'
        ),
        'gl' => array(
            'name' => 'Galician',
            'native' => 'Galego'
        ),
        'de' => array(
            'name' => 'German',
            'native' => 'Deutsch'
        ),
        'el' => array(
            'name' => 'Greek',
            'native' => '&#949;&#955;&#955;&#951;&#957;&#953;&#954;&#940;'
        ),
        'gu' => array(
            'name' => 'Gujarati',
            'native' => '&#2711;&#2753;&#2716;&#2736;&#2750;&#2724;&#2752;'
        ),
        'ha' => array(
            'name' => 'Hausa',
            'native' => '&#1581;&#1614;&#1608;&#1618;&#1587;&#1614;'
        ),
        'he' => array(
            'name' => 'Hebrew',
            'native' => '&#1506;&#1489;&#1512;&#1497;&#1514;'
        ),
        'hi' => array(
            'name' => 'Hindi',
            'native' => '&#2361;&#2367;&#2344;&#2381;&#2342;&#2368;'
        ),
        'hu' => array(
            'name' => 'Hungarian',
            'native' => 'magyar'
        ),
        'is' => array(
            'name' => 'Icelandic',
            'native' => '&Iacute;slenska'
        ),
        'io' => array(
            'name' => 'Ido',
            'native' => 'Ido'
        ),
        'id' => array(
            'name' => 'Indonesian',
            'native' => 'Bahasa Indonesia'
        ),
        'ga' => array(
            'name' => 'Irish',
            'native' => 'Irish'
        ),
        'it' => array(
            'name' => 'Italian',
            'native' => 'italiano'
        ),
        'ja' => array(
            'name' => 'Japanese',
            'native' => '&#26085;&#26412;&#35486;'
        ),
        'jv' => array(
            'name' => 'Javanese',
            'native' => 'basa Jawa'
        ),
        'ko' => array(
            'name' => 'Korean',
            'native' => '&#54620;&#44397;&#50612;'
        ),
        'ku' => array(
            'name' => 'Kurdish',
            'native' => 'Kurd&iacute;'
        ),
        'lv' => array(
            'name' => 'Latvian',
            'native' => 'latvie&#353;u'
        ),
        'lt' => array(
            'name' => 'Lithuanian',
            'native' => 'lietuvi&#353;kai'
        ),
        'lmo' => array(
            'name' => 'Lombard',
            'native' => 'Lombardo'
        ),
        'mk' => array(
            'name' => 'Macedonian',
            'native' => '&#1084;&#1072;&#1082;&#1077;&#1076;&#1086;&#1085;&#1089;&#1082;&#1080;'
        ),
        'mi' => array(
            'name' => 'te reo Māori',
            'native' => 'te reo Māori'
        ),
        'ms' => array(
            'name' => 'Malay',
            'native' => 'Bahasa melayu'
        ),
        'mt' => array(
            'name' => 'Maltese',
            'native' => 'Malti'
        ),
        'mr' => array(
            'name' => 'Marathi',
            'native' => '&#2350;&#2352;&#2366;&#2336;&#2368;'
        ),
        'ne' => array(
            'name' => 'Nepali',
            'native' => '&#2344;&#2375;&#2346;&#2366;&#2354;&#2368;'
        ),
        'nb' => array(
            'name' => 'Norwegian',
            'native' => 'Norsk'
        ),
        'om' => array(
            'name' => 'Oromo',
            'native' => 'Afaan Oromo'
        ),
        'fa' => array(
            'name' => 'Persian',
            'native' => '&#1601;&#1575;&#1585;&#1587;&#1609;'
        ),
        'pl' => array(
            'name' => 'Polish',
            'native' => 'polski'
        ),
        'pt_PT' => array(
            'name' => 'Portuguese (Portugal)',
            'native' => 'portugu&ecirc;s (Portugal)'
        ),
        'pt_BR' => array(
            'name' => 'Portuguese (Brazil)',
            'native' => 'portugu&ecirc;s (Brazil)'
        ),
        'pa' => array(
            'name' => 'Punjabi',
            'native' => '&#2602;&#2672;&#2588;&#2622;&#2604;&#2624;'
        ),
        'qu' => array(
            'name' => 'Quechua',
            'native' => 'Quechua'
        ),
        'rm' => array(
            'name' => 'Romansh',
            'native' => 'rumantsch'
        ),
        'ro' => array(
            'name' => 'Romanian',
            'native' => 'rom&acirc;n'
        ),
        'ru' => array(
            'name' => 'Russian',
            'native' => '&#1056;&#1091;&#1089;&#1089;&#1082;&#1080;&#1081;'
        ),
        'sco' => array(
            'name' => 'Scots',
            'native' => 'Scoats leid, Lallans'
        ),
        'sr' => array(
            'name' => 'Serbian',
            'native' => '&#1089;&#1088;&#1087;&#1089;&#1082;&#1080;'
        ),
        'sk' => array(
            'name' => 'Slovak',
            'native' => 'sloven&#269;ina'
        ),
        'sl' => array(
            'name' => 'Slovenian',
            'native' => 'sloven&#353;&#269;ina'
        ),
        'es' => array(
            'name' => 'Spanish',
            'native' => 'espa&ntilde;ol'
        ),
        'sv' => array(
            'name' => 'Swedish',
            'native' => 'Svenska'
        ),
        'tl' => array(
            'name' => 'Tagalog',
            'native' => 'Tagalog'
        ),
        'ta' => array(
            'name' => 'Tamil',
            'native' => '&#2980;&#2990;&#3007;&#2996;&#3021;'
        ),
        'te' => array(
            'name' => 'Telugu',
            'native' => '&#3108;&#3142;&#3122;&#3137;&#3095;&#3137;'
        ),
        'to' => array(
            'name' => 'Tonga',
            'native' => 'chiTonga'
        ),
        'ts' => array(
            'name' => 'Tsonga',
            'native' => 'xiTshonga'
        ),
        'tn' => array(
            'name' => 'Tswana',
            'native' => 'seTswana'
        ),
        'tr' => array(
            'name' => 'Turkish',
            'native' => 'T&uuml;rk&ccedil;e'
        ),
        'tk' => array(
            'name' => 'Turkmen',
            'native' => '&#1090;&#1199;&#1088;&#1082;m&#1077;&#1085;&#1095;&#1077;'
        ),
        'tw' => array(
            'name' => 'Twi',
            'native' => 'twi'
        ),
        'uk' => array(
            'name' => 'Ukrainian',
            'native' => '&#1059;&#1082;&#1088;&#1072;&#1111;&#1085;&#1089;&#1100;&#1082;&#1072;'
        ),
        'ur' => array(
            'name' => 'Urdu',
            'native' => '&#1575;&#1585;&#1583;&#1608;'
        ),
        'uz' => array(
            'name' => 'Uzbek',
            'native' => '&#1118;&#1079;&#1073;&#1077;&#1082;'
        ),
        've' => array(
            'name' => 'Venda',
            'native' => 'tshiVen&#x1E13;a'
        ),
        'vi' => array(
            'name' => 'Vietnamese',
            'native' => 'ti&#7871;ng vi&#7879;t'
        ),
        'wa' => array(
            'name' => 'Walloon',
            'native' => 'walon'
        ),
        'wo' => array(
            'name' => 'Wolof',
            'native' => 'Wollof'
        ),
        'xh' => array(
            'name' => 'Xhosa',
            'native' => 'isiXhosa'
        ),
        'yi' => array(
            'name' => 'Yiddish',
            'native' => '&#1522;&#1460;&#1491;&#1497;&#1513;'
        ),
        'zu' => array(
            'name' => 'Zulu',
            'native' => 'isiZulu'
        )
    );

    /**
     * @config
     * @var array $common_locales
     * Sorted alphabtically by the common language name,
     * not the locale key.
     */
    private static $common_locales = array(
        'af_ZA' => array(
            'name' => 'Afrikaans',
            'native' => 'Afrikaans'
        ),
        'sq_AL' => array(
            'name' => 'Albanian',
            'native' => 'shqip'
        ),
        'ar_EG' => array(
            'name' => 'Arabic',
            'native' => '&#1575;&#1604;&#1593;&#1585;&#1576;&#1610;&#1577;'
        ),
        'eu_ES' => array(
            'name' => 'Basque',
            'native' => 'euskera'
        ),
        'be_BY' => array(
            'name' => 'Belarusian',
            'native' =>
                '&#1041;&#1077;&#1083;&#1072;&#1088;&#1091;&#1089;&#1082;&#1072;&#1103; &#1084;&#1086;&#1074;&#1072;'
        ),
        'bn_BD' => array(
            'name' => 'Bengali',
            'native' => '&#2476;&#2494;&#2434;&#2482;&#2494;'
        ),
        'bg_BG' => array(
            'name' => 'Bulgarian',
            'native' => '&#1073;&#1098;&#1083;&#1075;&#1072;&#1088;&#1089;&#1082;&#1080;'
        ),
        'ca_ES' => array(
            'name' => 'Catalan',
            'native' => 'catal&agrave;'
        ),
        'zh_CN' => array(
            'name' => 'Chinese',
            'native' => '中国的'
        ),
        'zh_yue' => array(
            'name' => 'Chinese (Cantonese)',
            'native' => '&#24291;&#26481;&#35441; [&#24191;&#19996;&#35805;]'
        ),
        'zh_cmn' => array(
            'name' => 'Chinese (Mandarin)',
            'native' => '&#26222;&#36890;&#35441; [&#26222;&#36890;&#35805;]'
        ),
        'hr_HR' => array(
            'name' => 'Croatian',
            'native' => 'Hrvatski'
        ),
        'cs_CZ' => array(
            'name' => 'Czech',
            'native' => '&#x010D;e&#353;tina'
        ),
        'cy_GB' => array(
            'name' => 'Welsh',
            'native' => 'Welsh/Cymraeg'
        ),
        'da_DK' => array(
            'name' => 'Danish',
            'native' => 'dansk'
        ),
        'nl_NL' => array(
            'name' => 'Dutch',
            'native' => 'Nederlands'
        ),
        'nl_BE' => array(
            'name' => 'Dutch (Belgium)',
            'native' => 'Nederlands (Belgi&euml;)'
        ),
        'en_NZ' => array(
            'name' => 'English (NZ)',
            'native' => 'English (NZ)'
        ),
        'en_US' => array(
            'name' => 'English (US)',
            'native' => 'English (US)'
        ),
        'en_GB' => array(
            'name' => 'English (UK)',
            'native' => 'English (UK)'
        ),
        'eo_XX' => array(
            'name' => 'Esperanto',
            'native' => 'Esperanto'
        ),
        'et_EE' => array(
            'name' => 'Estonian',
            'native' => 'eesti keel'
        ),
        'fo_FO' => array(
            'name' => 'Faroese',
            'native' => 'F&oslash;royska'
        ),
        'fi_FI' => array(
            'name' => 'Finnish',
            'native' => 'suomi'
        ),
        'fr_FR' => array(
            'name' => 'French',
            'native' => 'fran&ccedil;ais'
        ),
        'fr_BE' => array(
            'name' => 'French (Belgium)',
            'native' => 'Fran&ccedil;ais (Belgique)'
        ),
        'gd_GB' => array(
            'name' => 'Gaelic',
            'native' => 'Gaeilge'
        ),
        'gl_ES' => array(
            'name' => 'Galician',
            'native' => 'Galego'
        ),
        'de_DE' => array(
            'name' => 'German',
            'native' => 'Deutsch'
        ),
        'el_GR' => array(
            'name' => 'Greek',
            'native' => '&#949;&#955;&#955;&#951;&#957;&#953;&#954;&#940;'
        ),
        'gu_IN' => array(
            'name' => 'Gujarati',
            'native' => '&#2711;&#2753;&#2716;&#2736;&#2750;&#2724;&#2752;'
        ),
        'ha_NG' => array(
            'name' => 'Hausa',
            'native' => '&#1581;&#1614;&#1608;&#1618;&#1587;&#1614;'
        ),
        'he_IL' => array(
            'name' => 'Hebrew',
            'native' => '&#1506;&#1489;&#1512;&#1497;&#1514;'
        ),
        'hi_IN' => array(
            'name' => 'Hindi',
            'native' => '&#2361;&#2367;&#2344;&#2381;&#2342;&#2368;'
        ),
        'hu_HU' => array(
            'name' => 'Hungarian',
            'native' => 'magyar'
        ),
        'is_IS' => array(
            'name' => 'Icelandic',
            'native' => '&Iacute;slenska'
        ),
        'id_ID' => array(
            'name' => 'Indonesian',
            'native' => 'Bahasa Indonesia'
        ),
        'ga_IE' => array(
            'name' => 'Irish',
            'native' => 'Irish'
        ),
        'it_IT' => array(
            'name' => 'Italian',
            'native' => 'italiano'
        ),
        'ja_JP' => array(
            'name' => 'Japanese',
            'native' => '&#26085;&#26412;&#35486;'
        ),
        'jv_ID' => array(
            'name' => 'Javanese',
            'native' => 'basa Jawa'
        ),
        'ko_KR' => array(
            'name' => 'Korean',
            'native' => '&#54620;&#44397;&#50612; [&#38867;&#22283;&#35486;]'
        ),
        'ku_IQ' => array(
            'name' => 'Kurdish',
            'native' => 'Kurd&iacute;'
        ),
        'lv_LV' => array(
            'name' => 'Latvian',
            'native' => 'latvie&#353;u'
        ),
        'lt_LT' => array(
            'name' => 'Lithuanian',
            'native' => 'lietuvi&#353;kai'
        ),
        'mk_MK' => array(
            'name' => 'Macedonian',
            'native' => '&#1084;&#1072;&#1082;&#1077;&#1076;&#1086;&#1085;&#1089;&#1082;&#1080;'
        ),
        'mi_NZ' => array(
            'name' => 'te reo Māori',
            'native' => 'te reo Māori'
        ),
        'ms_MY' => array(
            'name' => 'Malay',
            'native' => 'Bahasa melayu'
        ),
        'mt_MT' => array(
            'name' => 'Maltese',
            'native' => 'Malti'
        ),
        'mr_IN' => array(
            'name' => 'Marathi',
            'native' => '&#2350;&#2352;&#2366;&#2336;&#2368;'
        ),
        'ne_NP' => array(
            'name' => 'Nepali',
            'native' => '&#2344;&#2375;&#2346;&#2366;&#2354;&#2368;'
        ),
        'nb_NO' => array(
            'name' => 'Norwegian',
            'native' => 'Norsk'
        ),
        'om_ET' => array(
            'name' => 'Oromo',
            'native' => 'Afaan Oromo'
        ),
        'fa_IR' => array(
            'name' => 'Persian',
            'native' => '&#1601;&#1575;&#1585;&#1587;&#1609;'
        ),
        'pl_PL' => array(
            'name' => 'Polish',
            'native' => 'polski'
        ),
        'pt_PT' => array(
            'name' => 'Portuguese (Portugal)',
            'native' => 'portugu&ecirc;s (Portugal)'
        ),
        'pt_BR' => array(
            'name' => 'Portuguese (Brazil)',
            'native' => 'portugu&ecirc;s (Brazil)'
        ),
        'pa_IN' => array(
            'name' => 'Punjabi',
            'native' => '&#2602;&#2672;&#2588;&#2622;&#2604;&#2624;'
        ),
        'qu_PE' => array(
            'name' => 'Quechua',
            'native' => 'Quechua'
        ),
        'rm_CH' => array(
            'name' => 'Romansh',
            'native' => 'rumantsch'
        ),
        'ro_RO' => array(
            'name' => 'Romanian',
            'native' => 'rom&acirc;n'
        ),
        'ru_RU' => array(
            'name' => 'Russian',
            'native' => '&#1056;&#1091;&#1089;&#1089;&#1082;&#1080;&#1081;'
        ),
        'sco_SCO' => array(
            'name' => 'Scots',
            'native' => 'Scoats leid, Lallans'
        ),
        'sr_RS' => array(
            'name' => 'Serbian',
            'native' => '&#1089;&#1088;&#1087;&#1089;&#1082;&#1080;'
        ),
        'sk_SK' => array(
            'name' => 'Slovak',
            'native' => 'sloven&#269;ina'
        ),
        'sl_SI' => array(
            'name' => 'Slovenian',
            'native' => 'sloven&#353;&#269;ina'
        ),
        'es_ES' => array(
            'name' => 'Spanish',
            'native' => 'espa&ntilde;ol'
        ),
        'sv_SE' => array(
            'name' => 'Swedish',
            'native' => 'Svenska'
        ),
        'tl_PH' => array(
            'name' => 'Tagalog',
            'native' => 'Tagalog'
        ),
        'ta_IN' => array(
            'name' => 'Tamil',
            'native' => '&#2980;&#2990;&#3007;&#2996;&#3021;'
        ),
        'te_IN' => array(
            'name' => 'Telugu',
            'native' => '&#3108;&#3142;&#3122;&#3137;&#3095;&#3137;'
        ),
        'to_TO' => array(
            'name' => 'Tonga',
            'native' => 'chiTonga'
        ),
        'ts_ZA' => array(
            'name' => 'Tsonga',
            'native' => 'xiTshonga'
        ),
        'tn_ZA' => array(
            'name' => 'Tswana',
            'native' => 'seTswana'
        ),
        'tr_TR' => array(
            'name' => 'Turkish',
            'native' => 'T&uuml;rk&ccedil;e'
        ),
        'tk_TM' => array(
            'name' => 'Turkmen',
            'native' => '&#1090;&#1199;&#1088;&#1082;m&#1077;&#1085;&#1095;&#1077;'
        ),
        'tw_GH' => array(
            'name' => 'Twi',
            'native' => 'twi'
        ),
        'uk_UA' => array(
            'name' => 'Ukrainian',
            'native' => '&#1059;&#1082;&#1088;&#1072;&#1111;&#1085;&#1089;&#1100;&#1082;&#1072;'
        ),
        'ur_PK' => array(
            'name' => 'Urdu',
            'native' => '&#1575;&#1585;&#1583;&#1608;'
        ),
        'uz_UZ' => array(
            'name' => 'Uzbek',
            'native' => '&#1118;&#1079;&#1073;&#1077;&#1082;'
        ),
        've_ZA' => array(
            'name' => 'Venda',
            'native' => 'tshiVen&#x1E13;a'
        ),
        'vi_VN' => array(
            'name' => 'Vietnamese',
            'native' => 'ti&#7871;ng vi&#7879;t'
        ),
        'wo_SN' => array(
            'name' => 'Wolof',
            'native' => 'Wollof'
        ),
        'xh_ZA' => array(
            'name' => 'Xhosa',
            'native' => 'isiXhosa'
        ),
        'zu_ZA' => array(
            'name' => 'Zulu',
            'native' => 'isiZulu'
        ),
    );

    /**
     * @config
     * @var array
     */
    private static $tinymce_lang = array(
        'ar_EG' => 'ar',
        'ca_AD' => 'ca',
        'ca_ES' => 'ca',
        'cs_CZ' => 'cs',
        'cy_GB' => 'cy',
        'da_DK' => 'da',
        'da_GL' => 'da',
        'de_AT' => 'de',
        'de_BE' => 'de',
        'de_CH' => 'de',
        'de_DE' => 'de',
        'de_LI' => 'de',
        'de_LU' => 'de',
        'de_BR' => 'de',
        'de_US' => 'de',
        'el_CY' => 'el',
        'el_GR' => 'el',
        'es_AR' => 'es',
        'es_BO' => 'es',
        'es_CL' => 'es',
        'es_CO' => 'es',
        'es_CR' => 'es',
        'es_CU' => 'es',
        'es_DO' => 'es',
        'es_EC' => 'es',
        'es_ES' => 'es',
        'es_GQ' => 'es',
        'es_GT' => 'es',
        'es_HN' => 'es',
        'es_MX' => 'es',
        'es_NI' => 'es',
        'es_PA' => 'es',
        'es_PE' => 'es',
        'es_PH' => 'es',
        'es_PR' => 'es',
        'es_PY' => 'es',
        'es_SV' => 'es',
        'es_UY' => 'es',
        'es_VE' => 'es',
        'es_AD' => 'es',
        'es_BZ' => 'es',
        'es_US' => 'es',
        'fa_AF' => 'fa',
        'fa_IR' => 'fa',
        'fa_PK' => 'fa',
        'fi_FI' => 'fi',
        'fi_SE' => 'fi',
        'fr_BE' => 'fr',
        'fr_BF' => 'fr',
        'fr_BI' => 'fr',
        'fr_BJ' => 'fr',
        'fr_CA' => 'fr_ca',
        'fr_CF' => 'fr',
        'fr_CG' => 'fr',
        'fr_CH' => 'fr',
        'fr_CI' => 'fr',
        'fr_CM' => 'fr',
        'fr_DJ' => 'fr',
        'fr_DZ' => 'fr',
        'fr_FR' => 'fr',
        'fr_GA' => 'fr',
        'fr_GF' => 'fr',
        'fr_GN' => 'fr',
        'fr_GP' => 'fr',
        'fr_HT' => 'fr',
        'fr_KM' => 'fr',
        'fr_LU' => 'fr',
        'fr_MA' => 'fr',
        'fr_MC' => 'fr',
        'fr_MG' => 'fr',
        'fr_ML' => 'fr',
        'fr_MQ' => 'fr',
        'fr_MU' => 'fr',
        'fr_NC' => 'fr',
        'fr_NE' => 'fr',
        'fr_PF' => 'fr',
        'fr_PM' => 'fr',
        'fr_RE' => 'fr',
        'fr_RW' => 'fr',
        'fr_SC' => 'fr',
        'fr_SN' => 'fr',
        'fr_SY' => 'fr',
        'fr_TD' => 'fr',
        'fr_TG' => 'fr',
        'fr_TN' => 'fr',
        'fr_VU' => 'fr',
        'fr_WF' => 'fr',
        'fr_YT' => 'fr',
        'fr_GB' => 'fr',
        'fr_US' => 'fr',
        'he_IL' => 'he',
        'hu_HU' => 'hu',
        'hu_AT' => 'hu',
        'hu_RO' => 'hu',
        'hu_RS' => 'hu',
        'is_IS' => 'is',
        'it_CH' => 'it',
        'it_IT' => 'it',
        'it_SM' => 'it',
        'it_FR' => 'it',
        'it_HR' => 'it',
        'it_US' => 'it',
        'it_VA' => 'it',
        'ja_JP' => 'ja',
        'ko_KP' => 'ko',
        'ko_KR' => 'ko',
        'ko_CN' => 'ko',
        'mi_NZ' => 'mi_NZ',
        'nb_NO' => 'nb',
        'nb_SJ' => 'nb',
        'nl_AN' => 'nl',
        'nl_AW' => 'nl',
        'nl_BE' => 'nl',
        'nl_NL' => 'nl',
        'nl_SR' => 'nl',
        'nn_NO' => 'nn',
        'pl_PL' => 'pl',
        'pl_UA' => 'pl',
        'pt_AO' => 'pt',
        'pt_BR' => 'pt',
        'pt_CV' => 'pt',
        'pt_GW' => 'pt',
        'pt_MZ' => 'pt',
        'pt_PT' => 'pt',
        'pt_ST' => 'pt',
        'pt_TL' => 'pt',
        'ro_MD' => 'ro',
        'ro_RO' => 'ro',
        'ro_RS' => 'ro',
        'ru_BY' => 'ru',
        'ru_KG' => 'ru',
        'ru_KZ' => 'ru',
        'ru_RU' => 'ru',
        'ru_SJ' => 'ru',
        'ru_UA' => 'ru',
        'si_LK' => 'si',
        'sk_SK' => 'sk',
        'sk_RS' => 'sk',
        'sq_AL' => 'sq',
        'sr_BA' => 'sr',
        'sr_ME' => 'sr',
        'sr_RS' => 'sr',
        'sv_FI' => 'sv',
        'sv_SE' => 'sv',
        'tr_CY' => 'tr',
        'tr_TR' => 'tr',
        'tr_DE' => 'tr',
        'tr_MK' => 'tr',
        'uk_UA' => 'uk',
        'vi_VN' => 'vi',
        'vi_US' => 'vi',
        'zh_CN' => 'zh-cn',
        'zh_HK' => 'zh-cn',
        'zh_MO' => 'zh-cn',
        'zh_SG' => 'zh-cn',
        'zh_TW' => 'zh-tw',
        'zh_ID' => 'zh-cn',
        'zh_MY' => 'zh-cn',
        'zh_TH' => 'zh-cn',
        'zh_US' => 'zn-cn',

    );

    /**
     * @config
     * @var array $likely_subtags Provides you "likely locales"
     * for a given "short" language code. This is a guess,
     * as we can't disambiguate from e.g. "en" to "en_US" - it
     * could also mean "en_UK".
     * @see http://www.unicode.org/cldr/data/charts/supplemental/likely_subtags.html
     */
    private static $likely_subtags = array(
        'aa' => 'aa_ET',
        'ab' => 'ab_GE',
        'ady' => 'ady_RU',
        'af' => 'af_ZA',
        'ak' => 'ak_GH',
        'am' => 'am_ET',
        'ar' => 'ar_EG',
        'as' => 'as_IN',
        'ast' => 'ast_ES',
        'av' => 'av_RU',
        'ay' => 'ay_BO',
        'az' => 'az_AZ',
        'az_Cyrl' => 'az_AZ',
        'az_Arab' => 'az_IR',
        'az_IR' => 'az_IR',
        'ba' => 'ba_RU',
        'be' => 'be_BY',
        'bg' => 'bg_BG',
        'bi' => 'bi_VU',
        'bn' => 'bn_BD',
        'bo' => 'bo_CN',
        'bs' => 'bs_BA',
        'ca' => 'ca_ES',
        'ce' => 'ce_RU',
        'ceb' => 'ceb_PH',
        'ch' => 'ch_GU',
        'chk' => 'chk_FM',
        'crk' => 'crk_CA',
        'cs' => 'cs_CZ',
        'cwd' => 'cwd_CA',
        'cy' => 'cy_GB',
        'da' => 'da_DK',
        'de' => 'de_DE',
        'dv' => 'dv_MV',
        'dz' => 'dz_BT',
        'ee' => 'ee_GH',
        'efi' => 'efi_NG',
        'el' => 'el_GR',
        'en' => 'en_US',
        'es' => 'es_ES',
        'et' => 'et_EE',
        'eu' => 'eu_ES',
        'eo' => 'eo_XX',
        'fa' => 'fa_IR',
        'fi' => 'fi_FI',
        'fil' => 'fil_PH',
        'fj' => 'fj_FJ',
        'fo' => 'fo_FO',
        'fr' => 'fr_FR',
        'fur' => 'fur_IT',
        'fy' => 'fy_NL',
        'ga' => 'ga_IE',
        'gaa' => 'gaa_GH',
        'gd' => 'gd_GB',
        'gil' => 'gil_KI',
        'gl' => 'gl_ES',
        'gn' => 'gn_PY',
        'gu' => 'gu_IN',
        'ha' => 'ha_NG',
        'ha_Arab' => 'ha_SD',
        'ha_SD' => 'ha_SD',
        'haw' => 'haw_US',
        'he' => 'he_IL',
        'hi' => 'hi_IN',
        'hil' => 'hil_PH',
        'ho' => 'ho_PG',
        'hr' => 'hr_HR',
        'ht' => 'ht_HT',
        'hu' => 'hu_HU',
        'hy' => 'hy_AM',
        'id' => 'id_ID',
        'ig' => 'ig_NG',
        'ii' => 'ii_CN',
        'ilo' => 'ilo_PH',
        'inh' => 'inh_RU',
        'is' => 'is_IS',
        'it' => 'it_IT',
        'iu' => 'iu_CA',
        'ja' => 'ja_JP',
        'jv' => 'jv_ID',
        'ka' => 'ka_GE',
        'kaj' => 'kaj_NG',
        'kam' => 'kam_KE',
        'kbd' => 'kbd_RU',
        'kha' => 'kha_IN',
        'kk' => 'kk_KZ',
        'kl' => 'kl_GL',
        'km' => 'km_KH',
        'kn' => 'kn_IN',
        'ko' => 'ko_KR',
        'koi' => 'koi_RU',
        'kok' => 'kok_IN',
        'kos' => 'kos_FM',
        'kpe' => 'kpe_LR',
        'kpv' => 'kpv_RU',
        'krc' => 'krc_RU',
        'ks' => 'ks_IN',
        'ku' => 'ku_IQ',
        'ku_Latn' => 'ku_TR',
        'ku_TR' => 'ku_TR',
        'kum' => 'kum_RU',
        'kxm' => 'kxm_TH',
        'ky' => 'ky_KG',
        'la' => 'la_VA',
        'lah' => 'lah_PK',
        'lb' => 'lb_LU',
        'lbe' => 'lbe_RU',
        'lez' => 'lez_RU',
        'ln' => 'ln_CD',
        'lo' => 'lo_LA',
        'lt' => 'lt_LT',
        'lv' => 'lv_LV',
        'mai' => 'mai_IN',
        'mdf' => 'mdf_RU',
        'mdh' => 'mdh_PH',
        'mg' => 'mg_MG',
        'mh' => 'mh_MH',
        'mi' => 'mi_NZ',
        'mk' => 'mk_MK',
        'ml' => 'ml_IN',
        'mn' => 'mn_MN',
        'mn_CN' => 'mn_CN',
        'mn_Mong' => 'mn_CN',
        'mr' => 'mr_IN',
        'ms' => 'ms_MY',
        'mt' => 'mt_MT',
        'my' => 'my_MM',
        'myv' => 'myv_RU',
        'na' => 'na_NR',
        'nb' => 'nb_NO',
        'ne' => 'ne_NP',
        'niu' => 'niu_NU',
        'nl' => 'nl_NL',
        'nn' => 'nn_NO',
        'nr' => 'nr_ZA',
        'nso' => 'nso_ZA',
        'ny' => 'ny_MW',
        'om' => 'om_ET',
        'or' => 'or_IN',
        'os' => 'os_GE',
        'pa' => 'pa_IN',
        'pa_Arab' => 'pa_PK',
        'pa_PK' => 'pa_PK',
        'pag' => 'pag_PH',
        'pap' => 'pap_AN',
        'pau' => 'pau_PW',
        'pl' => 'pl_PL',
        'pon' => 'pon_FM',
        'ps' => 'ps_AF',
        'pt' => 'pt_BR',
        'qu' => 'qu_PE',
        'rm' => 'rm_CH',
        'rn' => 'rn_BI',
        'ro' => 'ro_RO',
        'ru' => 'ru_RU',
        'rw' => 'rw_RW',
        'sa' => 'sa_IN',
        'sah' => 'sah_RU',
        'sat' => 'sat_IN',
        'sd' => 'sd_IN',
        'se' => 'se_NO',
        'sg' => 'sg_CF',
        'si' => 'si_LK',
        'sid' => 'sid_ET',
        'sk' => 'sk_SK',
        'sl' => 'sl_SI',
        'sm' => 'sm_WS',
        'sn' => 'sn_ZW',
        'so' => 'so_SO',
        'sq' => 'sq_AL',
        'sr' => 'sr_RS',
        'ss' => 'ss_ZA',
        'st' => 'st_ZA',
        'su' => 'su_ID',
        'sv' => 'sv_SE',
        'sw' => 'sw_TZ',
        'swb' => 'swb_KM',
        'ta' => 'ta_IN',
        'te' => 'te_IN',
        'tet' => 'tet_TL',
        'tg' => 'tg_TJ',
        'th' => 'th_TH',
        'ti' => 'ti_ET',
        'tig' => 'tig_ER',
        'tk' => 'tk_TM',
        'tkl' => 'tkl_TK',
        'tl' => 'tl_PH',
        'tn' => 'tn_ZA',
        'to' => 'to_TO',
        'tpi' => 'tpi_PG',
        'tr' => 'tr_TR',
        'trv' => 'trv_TW',
        'ts' => 'ts_ZA',
        'tsg' => 'tsg_PH',
        'tt' => 'tt_RU',
        'tts' => 'tts_TH',
        'tvl' => 'tvl_TV',
        'tw' => 'tw_GH',
        'ty' => 'ty_PF',
        'tyv' => 'tyv_RU',
        'udm' => 'udm_RU',
        'ug' => 'ug_CN',
        'uk' => 'uk_UA',
        'uli' => 'uli_FM',
        'und' => 'en_US',
        'und_AD' => 'ca_AD',
        'und_AE' => 'ar_AE',
        'und_AF' => 'fa_AF',
        'und_AL' => 'sq_AL',
        'und_AM' => 'hy_AM',
        'und_AN' => 'pap_AN',
        'und_AO' => 'pt_AO',
        'und_AR' => 'es_AR',
        'und_AS' => 'sm_AS',
        'und_AT' => 'de_AT',
        'und_AW' => 'nl_AW',
        'und_AX' => 'sv_AX',
        'und_AZ' => 'az_AZ',
        'und_Arab' => 'ar_EG',
        'und_Arab_CN' => 'ug_CN',
        'und_Arab_DJ' => 'ar_DJ',
        'und_Arab_ER' => 'ar_ER',
        'und_Arab_IL' => 'ar_IL',
        'und_Arab_IN' => 'ur_IN',
        'und_Arab_PK' => 'ur_PK',
        'und_Armn' => 'hy_AM',
        'und_BA' => 'bs_BA',
        'und_BD' => 'bn_BD',
        'und_BE' => 'nl_BE',
        'und_BF' => 'fr_BF',
        'und_BG' => 'bg_BG',
        'und_BH' => 'ar_BH',
        'und_BI' => 'rn_BI',
        'und_BJ' => 'fr_BJ',
        'und_BL' => 'fr_BL',
        'und_BN' => 'ms_BN',
        'und_BO' => 'es_BO',
        'und_BR' => 'pt_BR',
        'und_BT' => 'dz_BT',
        'und_BY' => 'be_BY',
        'und_Beng' => 'bn_BD',
        'und_CD' => 'fr_CD',
        'und_CF' => 'sg_CF',
        'und_CG' => 'ln_CG',
        'und_CH' => 'de_CH',
        'und_CI' => 'fr_CI',
        'und_CL' => 'es_CL',
        'und_CM' => 'fr_CM',
        'und_CN' => 'zh_CN',
        'und_CO' => 'es_CO',
        'und_CR' => 'es_CR',
        'und_CU' => 'es_CU',
        'und_CV' => 'pt_CV',
        'und_CY' => 'el_CY',
        'und_CZ' => 'cs_CZ',
        'und_Cans' => 'cwd_CA',
        'und_Cyrl' => 'ru_RU',
        'und_Cyrl_BA' => 'sr_BA',
        'und_Cyrl_GE' => 'ab_GE',
        'und_DE' => 'de_DE',
        'und_DJ' => 'aa_DJ',
        'und_DK' => 'da_DK',
        'und_DO' => 'es_DO',
        'und_DZ' => 'ar_DZ',
        'und_Deva' => 'hi_IN',
        'und_EC' => 'es_EC',
        'und_EE' => 'et_EE',
        'und_EG' => 'ar_EG',
        'und_EH' => 'ar_EH',
        'und_ER' => 'ti_ER',
        'und_ES' => 'es_ES',
        'und_ET' => 'am_ET',
        'und_Ethi' => 'am_ET',
        'und_FI' => 'fi_FI',
        'und_FJ' => 'fj_FJ',
        'und_FM' => 'chk_FM',
        'und_FO' => 'fo_FO',
        'und_FR' => 'fr_FR',
        'und_GA' => 'fr_GA',
        'und_GE' => 'ka_GE',
        'und_GF' => 'fr_GF',
        'und_GH' => 'ak_GH',
        'und_GL' => 'kl_GL',
        'und_GN' => 'fr_GN',
        'und_GP' => 'fr_GP',
        'und_GQ' => 'fr_GQ',
        'und_GR' => 'el_GR',
        'und_GT' => 'es_GT',
        'und_GU' => 'ch_GU',
        'und_GW' => 'pt_GW',
        'und_Geor' => 'ka_GE',
        'und_Grek' => 'el_GR',
        'und_Gujr' => 'gu_IN',
        'und_Guru' => 'pa_IN',
        'und_HK' => 'zh_HK',
        'und_HN' => 'es_HN',
        'und_HR' => 'hr_HR',
        'und_HT' => 'ht_HT',
        'und_HU' => 'hu_HU',
        'und_Hani' => 'zh_CN',
        'und_Hans' => 'zh_CN',
        'und_Hant' => 'zh_TW',
        'und_Hebr' => 'he_IL',
        'und_ID' => 'id_ID',
        'und_IL' => 'he_IL',
        'und_IN' => 'hi_IN',
        'und_IQ' => 'ar_IQ',
        'und_IR' => 'fa_IR',
        'und_IS' => 'is_IS',
        'und_IT' => 'it_IT',
        'und_JO' => 'ar_JO',
        'und_JP' => 'ja_JP',
        'und_Jpan' => 'ja_JP',
        'und_KG' => 'ky_KG',
        'und_KH' => 'km_KH',
        'und_KM' => 'ar_KM',
        'und_KP' => 'ko_KP',
        'und_KR' => 'ko_KR',
        'und_KW' => 'ar_KW',
        'und_KZ' => 'ru_KZ',
        'und_Khmr' => 'km_KH',
        'und_Knda' => 'kn_IN',
        'und_Kore' => 'ko_KR',
        'und_LA' => 'lo_LA',
        'und_LB' => 'ar_LB',
        'und_LI' => 'de_LI',
        'und_LK' => 'si_LK',
        'und_LS' => 'st_LS',
        'und_LT' => 'lt_LT',
        'und_LU' => 'fr_LU',
        'und_LV' => 'lv_LV',
        'und_LY' => 'ar_LY',
        'und_Laoo' => 'lo_LA',
        'und_Latn_CN' => 'ii_CN',
        'und_Latn_CY' => 'tr_CY',
        'und_Latn_DZ' => 'fr_DZ',
        'und_Latn_ET' => 'om_ET',
        'und_Latn_KM' => 'fr_KM',
        'und_Latn_MA' => 'fr_MA',
        'und_Latn_MK' => 'sq_MK',
        'und_Latn_SY' => 'fr_SY',
        'und_Latn_TD' => 'fr_TD',
        'und_Latn_TN' => 'fr_TN',
        'und_MA' => 'ar_MA',
        'und_MC' => 'fr_MC',
        'und_MD' => 'ro_MD',
        'und_ME' => 'sr_ME',
        'und_MF' => 'fr_MF',
        'und_MG' => 'mg_MG',
        'und_MH' => 'mh_MH',
        'und_MK' => 'mk_MK',
        'und_ML' => 'fr_ML',
        'und_MM' => 'my_MM',
        'und_MN' => 'mn_MN',
        'und_MO' => 'zh_MO',
        'und_MQ' => 'fr_MQ',
        'und_MR' => 'ar_MR',
        'und_MT' => 'mt_MT',
        'und_MV' => 'dv_MV',
        'und_MW' => 'ny_MW',
        'und_MX' => 'es_MX',
        'und_MY' => 'ms_MY',
        'und_MZ' => 'pt_MZ',
        'und_Mlym' => 'ml_IN',
        'und_Mong' => 'mn_CN',
        'und_Mymr' => 'my_MM',
        'und_NC' => 'fr_NC',
        'und_NE' => 'ha_NE',
        'und_NG' => 'ha_NG',
        'und_NI' => 'es_NI',
        'und_NL' => 'nl_NL',
        'und_NO' => 'nb_NO',
        'und_NP' => 'ne_NP',
        'und_NR' => 'na_NR',
        'und_NU' => 'niu_NU',
        'und_OM' => 'ar_OM',
        'und_Orya' => 'or_IN',
        'und_PA' => 'es_PA',
        'und_PE' => 'es_PE',
        'und_PF' => 'ty_PF',
        'und_PG' => 'tpi_PG',
        'und_PH' => 'fil_PH',
        'und_PK' => 'ur_PK',
        'und_PL' => 'pl_PL',
        'und_PM' => 'fr_PM',
        'und_PR' => 'es_PR',
        'und_PS' => 'ar_PS',
        'und_PT' => 'pt_PT',
        'und_PW' => 'pau_PW',
        'und_PY' => 'gn_PY',
        'und_QA' => 'ar_QA',
        'und_RE' => 'fr_RE',
        'und_RO' => 'ro_RO',
        'und_RS' => 'sr_RS',
        'und_RU' => 'ru_RU',
        'und_RW' => 'rw_RW',
        'und_SA' => 'ar_SA',
        'und_SD' => 'ar_SD',
        'und_SE' => 'sv_SE',
        'und_SI' => 'sl_SI',
        'und_SJ' => 'nb_SJ',
        'und_SK' => 'sk_SK',
        'und_SM' => 'it_SM',
        'und_SN' => 'fr_SN',
        'und_SO' => 'so_SO',
        'und_SR' => 'nl_SR',
        'und_ST' => 'pt_ST',
        'und_SV' => 'es_SV',
        'und_SY' => 'ar_SY',
        'und_Sinh' => 'si_LK',
        'und_TD' => 'ar_TD',
        'und_TG' => 'ee_TG',
        'und_TH' => 'th_TH',
        'und_TJ' => 'tg_TJ',
        'und_TK' => 'tkl_TK',
        'und_TL' => 'tet_TL',
        'und_TM' => 'tk_TM',
        'und_TN' => 'ar_TN',
        'und_TO' => 'to_TO',
        'und_TR' => 'tr_TR',
        'und_TV' => 'tvl_TV',
        'und_TW' => 'zh_TW',
        'und_Taml' => 'ta_IN',
        'und_Telu' => 'te_IN',
        'und_Thaa' => 'dv_MV',
        'und_Thai' => 'th_TH',
        'und_Tibt' => 'bo_CN',
        'und_UA' => 'uk_UA',
        'und_UY' => 'es_UY',
        'und_UZ' => 'uz_UZ',
        'und_VA' => 'la_VA',
        'und_VE' => 'es_VE',
        'und_VN' => 'vi_VN',
        'und_VU' => 'fr_VU',
        'und_WF' => 'fr_WF',
        'und_WS' => 'sm_WS',
        'und_YE' => 'ar_YE',
        'und_YT' => 'fr_YT',
        'und_ZW' => 'sn_ZW',
        'ur' => 'ur_PK',
        'uz' => 'uz_UZ',
        'uz_AF' => 'uz_AF',
        'uz_Arab' => 'uz_AF',
        've' => 've_ZA',
        'vi' => 'vi_VN',
        'wal' => 'wal_ET',
        'war' => 'war_PH',
        'wo' => 'wo_SN',
        'xh' => 'xh_ZA',
        'yap' => 'yap_FM',
        'yo' => 'yo_NG',
        'za' => 'za_CN',
        'zh' => 'zh_CN',
        'zh_HK' => 'zh_HK',
        'zh_Hani' => 'zh_CN',
        'zh_Hant' => 'zh_TW',
        'zh_MO' => 'zh_MO',
        'zh_TW' => 'zh_TW',
        'zu' => 'zu_ZA',
    );

    /**
     * Map of rails plurals into standard order (fewest to most)
     * Note: Default locale only supplies one|other, but non-default locales
     * can specify custom plurals.
     *
     * @config
     * @var array
     */
    private static $plurals = [
        'zero',
        'one',
        'two',
        'few',
        'many',
        'other',
    ];

    /**
     * Plural forms in default (en) locale
     *
     * @var array
     */
    private static $default_plurals = [
        'one',
        'other',
    ];

    /**
     * Warn if _t() invoked without a default.
     *
     * @config
     * @var bool
     */
    private static $missing_default_warning = true;

    /**
     * This is the main translator function. Returns the string defined by $entity according to the
     * currently set locale.
     *
     * Also supports pluralisation of strings. Pass in a `count` argument, as well as a
     * default value with `|` pipe-delimited options for each plural form.
     *
     * @param string $entity Entity that identifies the string. It must be in the form
     * "Namespace.Entity" where Namespace will be usually the class name where this
     * string is used and Entity identifies the string inside the namespace.
     * @param mixed $arg,... Additional arguments are parsed as such:
     *  - Next string argument is a default. Pass in a `|` pipe-delimited value with `{count}`
     *    to do pluralisation.
     *  - Any other string argument after default is context for i18nTextCollector
     *  - Any array argument in any order is an injection parameter list. Pass in a `count`
     *    injection parameter to pluralise.
     * @return string
     */
    public static function _t($entity, $arg = null)
    {
        // Detect args
        $default = null;
        $injection = [];
        foreach (array_slice(func_get_args(), 1) as $arg) {
            if (is_array($arg)) {
                $injection = $arg;
            } elseif (!isset($default)) {
                $default = $arg ?: '';
            }
        }

        // Encourage the provision of default values so that text collector can discover new strings
        if (!$default && static::config()->get('missing_default_warning')) {
            user_error("Missing default for localisation key $entity", E_USER_WARNING);
        }

        // Deprecate legacy injection format (`string %s, %d`)
        // inject the variables from injectionArray (if present)
        $sprintfArgs = [];
        if ($default && !preg_match('/\{[\w\d]*\}/i', $default) && preg_match('/%[s,d]/', $default)) {
            Deprecation::notice('5.0', 'sprintf style localisation variables are deprecated');
            $sprintfArgs = array_values($injection);
            $injection = [];
        }

        // If injection isn't associative, assume legacy injection format
        $failUnlessSprintf = false;
        if ($injection && array_values($injection) === $injection) {
            $failUnlessSprintf = true; // Note: Will trigger either a deprecation error or exception below
            $sprintfArgs = array_values($injection);
            $injection = [];
        }

        // Detect plurals: Has a {count} argument as well as a `|` pipe delimited string (if provided)
        $isPlural = isset($injection['count']);
        $count = $isPlural ? $injection['count'] : null;
        // Refine check against default
        if ($isPlural && $default && !static::parse_plurals($default)) {
            $isPlural = false;
        }

        // Pass back to translation backend
        if ($isPlural) {
            $result = static::getMessageProvider()->pluralise($entity, $default, $injection, $count);
        } else {
            $result = static::getMessageProvider()->translate($entity, $default, $injection);
        }

        // Sometimes default is omitted, so we don't know we have %s injection format until after translation
        if (!$default && !preg_match('/\{[\w\d]*\}/i', $result) && preg_match('/%[s,d]/', $result)) {
            Deprecation::notice('5.0', 'sprintf style localisation is deprecated');
            if ($injection) {
                $sprintfArgs = array_values($injection);
            }
        } elseif ($failUnlessSprintf) {
            // Note: After removing deprecated code, you can move this error up into the is-associative check
            // Neither default nor translated strings were %s substituted, and our array isn't associative
            throw new InvalidArgumentException('Injection must be an associative array');
        }

        // @deprecated (see above)
        if ($sprintfArgs) {
            return vsprintf($result, $sprintfArgs);
        }

        return $result;
    }

    /**
     * Split plural string into standard CLDR array form.
     * A string is considered a pluralised form if it has a {count} argument, and
     * a single `|` pipe-delimiting character.
     *
     * Note: Only splits in the default (en) locale as the string form contains limited metadata.
     *
     * @param string $string Input string
     * @return array List of plural forms, or empty array if not plural
     */
    public static function parse_plurals($string)
    {
        if (strstr($string, '|') && strstr($string, '{count}')) {
            $keys = i18n::config()->get('default_plurals');
            $values = explode('|', $string);
            if (count($keys) == count($values)) {
                return array_combine($keys, $values);
            }
        }
        return [];
    }

    /**
     * Convert CLDR array plural form to `|` pipe-delimited string.
     * Unlike parse_plurals, this supports all locale forms (not just en)
     *
     * @param array $plurals
     * @return string Delimited string, or null if not plurals
     */
    public static function encode_plurals($plurals)
    {
        // Validate against global plural list
        $forms = i18n::config()->get('plurals');
        $forms = array_combine($forms, $forms);
        $intersect = array_intersect_key($plurals, $forms);
        if ($intersect) {
            return implode('|', $intersect);
        }
        return null;
    }

    /**
     * Get a list of commonly used languages
     *
     * @param bool $native Use native names for languages instead of English ones
     * @return array list of languages in the form 'code' => 'name'
     */
    public static function get_common_languages($native = false)
    {
        $languages = array();
        foreach (i18n::config()->get('common_languages') as $code => $name) {
            $languages[$code] = ($native ? $name['native'] : $name['name']);
        }
        return $languages;
    }

    /**
     * Get a list of commonly used locales
     *
     * @param bool $native Use native names for locale instead of English ones
     * @return array list of languages in the form 'code' => 'name'
     */
    public static function get_common_locales($native = false)
    {
        $languages = array();
        foreach (i18n::config()->get('common_locales') as $code => $name) {
            $languages[$code] = ($native ? $name['native'] : $name['name']);
        }
        return $languages;
    }

    /**
     * Matches a given locale with the closest translation available in the system
     *
     * @param string $locale locale code
     * @return string Locale of closest available translation, if available
     */
    public static function get_closest_translation($locale)
    {
        // Check if exact match
        $pool = self::get_existing_translations();
        if (isset($pool[$locale])) {
            return $locale;
        }

        // Fallback to best locale for common language
        $lang = self::get_lang_from_locale($locale);
        $candidate = self::get_locale_from_lang($lang);
        if (isset($pool[$candidate])) {
            return $candidate;
        }
        return null;
    }

    /**
     * Searches the root-directory for module-directories
     * (identified by having a _config.php on their first directory-level).
     * Finds locales by filename convention ("<locale>.<extension>", e.g. "de_AT.yml").
     *
     * @return array
     */
    public static function get_existing_translations()
    {
        $locales = array();
        foreach (static::get_lang_dirs() as $langPath) {
            $allLocales = i18n::config()->get('all_locales');
            $langFiles = scandir($langPath);
            foreach ($langFiles as $langFile) {
                $locale = pathinfo($langFile, PATHINFO_FILENAME);
                $ext = pathinfo($langFile, PATHINFO_EXTENSION);
                if ($locale && $ext === 'yml') {
                    // Normalize locale to include likely region tag, avoid repetition in locale labels
                    $fullLocale = self::get_locale_from_lang($locale);
                    if (isset($allLocales[$fullLocale])) {
                        $locales[$fullLocale] = $allLocales[$fullLocale];
                    }
                }
            }
        }

        // sort by title (not locale)
        asort($locales);

        return $locales;
    }

    /**
     * Get a name from a language code (two characters, e.g. "en").
     *
     * @see get_locale_name()
     *
     * @param mixed $code Language code
     * @param boolean $native If true, the native name will be returned
     * @return string Name of the language
     */
    public static function get_language_name($code, $native = false)
    {
        $langs = i18n::config()->get('common_languages');
        if ($native) {
            return (isset($langs[$code]['native'])) ? $langs[$code]['native'] : false;
        } else {
            return (isset($langs[$code]['name'])) ? $langs[$code]['name'] : false;
        }
    }

    /**
     * Get a name from a locale code (xx_YY).
     *
     * @see get_language_name()
     *
     * @param string $code locale code
     * @return string Name of the locale
     */
    public static function get_locale_name($code)
    {
        $langs = self::config()->all_locales;
        return isset($langs[$code]) ? $langs[$code] : false;
    }

    /**
     * Get a code from an English language name
     *
     * @param string $name Name of the language
     * @return string Language code (if the name is not found, it'll return the passed name)
     */
    public static function get_language_code($name)
    {
        $code = array_search($name, self::get_common_languages());
        return ($code ? $code : $name);
    }

    /**
     * Get the current tinyMCE language
     *
     * @return string Language
     */
    public static function get_tinymce_lang()
    {
        $lang = i18n::config()->get('tinymce_lang');
        if (isset($lang[self::get_locale()])) {
            return $lang[self::get_locale()];
        }

        return 'en';
    }

    /**
     * Returns the "short" language name from a locale,
     * e.g. "en_US" would return "en".
     *
     * @param string $locale E.g. "en_US"
     * @return string Short language code, e.g. "en"
     */
    public static function get_lang_from_locale($locale)
    {
        return preg_replace('/(_|-).*/', '', $locale);
    }

    /**
     * Provides you "likely locales"
     * for a given "short" language code. This is a guess,
     * as we can't disambiguate from e.g. "en" to "en_US" - it
     * could also mean "en_UK". Based on the Unicode CLDR
     * project.
     * @see http://www.unicode.org/cldr/data/charts/supplemental/likely_subtags.html
     *
     * @param string $lang Short language code, e.g. "en"
     * @return string Long locale, e.g. "en_US"
     */
    public static function get_locale_from_lang($lang)
    {
        $subtags = i18n::config()->get('likely_subtags');
        if (preg_match('/\-|_/', $lang)) {
            return str_replace('-', '_', $lang);
        } elseif (isset($subtags[$lang])) {
            return $subtags[$lang];
        } else {
            return $lang . '_' . strtoupper($lang);
        }
    }

    /**
     * Gets a RFC 1766 compatible language code,
     * e.g. "en-US".
     *
     * @see http://www.ietf.org/rfc/rfc1766.txt
     * @see http://tools.ietf.org/html/rfc2616#section-3.10
     *
     * @param string $locale
     * @return string
     */
    public static function convert_rfc1766($locale)
    {
        return str_replace('_', '-', $locale);
    }

    /**
     * Given a PHP class name, finds the module where it's located.
     *
     * @param  string $name
     * @return string
     */
    public static function get_owner_module($name)
    {
        $manifest = ClassLoader::instance()->getManifest();
        $path     = $manifest->getItemPath($name);

        if (!$path) {
            return false;
        }

        $path = Director::makeRelative($path);
        $path = str_replace('\\', '/', $path);

        $parts = explode('/', trim($path, '/'));
        return array_shift($parts);
    }

    /**
     * Validates a "long" locale format (e.g. "en_US")
     * by checking it against {@link $all_locales}.
     *
     * To add a locale to {@link $all_locales}, use the following example
     * in your mysite/_config.php:
     * <code>
     * i18n::$allowed_locales['xx_XX'] = '<Language name>';
     * </code>
     *
     * Note: Does not check for {@link $allowed_locales}.
     *
     * @param string $locale
     * @return bool
     */
    public static function validate_locale($locale)
    {
        // Convert en-US to en_US
        $locale = str_replace('-', '_', $locale);
        return array_key_exists($locale, i18n::config()->get('all_locales'));
    }

    /**
     * Set the current locale, used as the default for
     * any localized classes, such as {@link FormField} or {@link DBField}
     * instances. Locales can also be persisted in {@link Member->Locale},
     * for example in the {@link CMSMain} interface the Member locale
     * overrules the global locale value set here.
     *
     * @param string $locale Locale to be set. See
     *                       http://unicode.org/cldr/data/diff/supplemental/languages_and_territories.html for a list
     *                       of possible locales.
     */
    public static function set_locale($locale)
    {
        if ($locale) {
            self::$current_locale = $locale;
        }
    }

    /**
     * Get the current locale.
     * Used by {@link Member::populateDefaults()}
     *
     * @return string Current locale in the system
     */
    public static function get_locale()
    {
        return self::$current_locale ?: i18n::config()->get('default_locale');
    }

    /**
     * Returns the script direction in format compatible with the HTML "dir" attribute.
     *
     * @see http://www.w3.org/International/tutorials/bidi-xhtml/
     * @param string $locale Optional locale incl. region (underscored)
     * @return string "rtl" or "ltr"
     */
    public static function get_script_direction($locale = null)
    {
        $dirs = static::config()->get('text_direction');
        if (!$locale) {
            $locale = i18n::get_locale();
        }
        if (isset($dirs[$locale])) {
            return $dirs[$locale];
        }
        $lang = static::get_lang_from_locale($locale);
        if (isset($dirs[$lang])) {
            return $dirs[$lang];
        }
        return 'ltr';
    }

    /**
     * Get sorted modules
     *
     * @return array Array of module names -> path
     */
    public static function get_sorted_modules()
    {
        // Get list of module => path pairs, and then just the names
        $modules = ClassLoader::instance()->getManifest()->getModules();
        $moduleNames = array_keys($modules);

        // Remove the "project" module from the list - we'll add it back specially later if needed
        global $project;
        if (($idx = array_search($project, $moduleNames)) !== false) {
            array_splice($moduleNames, $idx, 1);
        }

        // Get the order from the config syste (lowest to highest)
        $order = i18n::config()->get('module_priority');

        // Find all modules that don't have their order specified by the config system
        $unspecified = array_diff($moduleNames, $order);

        // If the placeholder "other_modules" exists in the order array, replace it by the unspecified modules
        if (($idx = array_search('other_modules', $order)) !== false) {
            array_splice($order, $idx, 1, $unspecified);
        } else {
            // Otherwise just jam them on the front
            array_splice($order, 0, 0, $unspecified);
        }

        // Put the project at end (highest priority)
        if (!in_array($project, $order)) {
            $order[] = $project;
        }

        $sortedModules = array();
        foreach ($order as $module) {
            if (isset($modules[$module])) {
                $sortedModules[$module] = $modules[$module];
            }
        }
        $sortedModules = array_reverse($sortedModules, true);
        return $sortedModules;
    }

    /**
     * Find the list of prioritised /lang folders in this application
     *
     * @return array
     */
    public static function get_lang_dirs()
    {
        $paths = [];

        // Search sorted modules
        foreach (static::get_sorted_modules() as $module => $path) {
            $langPath = "{$path}/lang/";
            if (is_dir($langPath)) {
                $paths[] = $langPath;
            }
        }

        // Search theme dirs
        $locator = ThemeResourceLoader::instance();
        foreach (SSViewer::get_themes() as $theme) {
            if ($locator->getSet($theme)) {
                continue;
            }
            $path = $locator->getPath($theme);
            $langPath = "{$path}/lang/";
            if (is_dir($langPath)) {
                $paths[] = $langPath;
            }
        }

        return $paths;
    }

    public static function get_template_global_variables()
    {
        return array(
            'i18nLocale' => 'get_locale',
            'get_locale',
            'i18nScriptDirection' => 'get_script_direction',
        );
    }

    /**
     * @return MessageProvider
     */
    public static function getMessageProvider()
    {
        return Injector::inst()->get(MessageProvider::class);
    }
}
