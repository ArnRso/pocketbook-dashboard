#include "../src/config.h"
#include "check.h"

static void parses_a_complete_file(void)
{
    pbd_config config;

    pbd_config_status status = pbd_config_parse("# commentaire\n"
                                                "\n"
                                                "  server_url = https://pocketbook.example.com/  \r\n"
                                                "token=pbd_abc\n",
        &config);

    CHECK_INT(PBD_CONFIG_OK, status);
    CHECK_STR("https://pocketbook.example.com", config.server_url);
    CHECK_STR("pbd_abc", config.token);
}

static void parses_a_file_without_final_newline(void)
{
    pbd_config config;

    CHECK_INT(PBD_CONFIG_OK, pbd_config_parse("token = pbd_abc\nserver_url = http://192.168.1.40:8080", &config));
    CHECK_STR("http://192.168.1.40:8080", config.server_url);
}

static void ignores_unknown_keys_and_lines_without_value(void)
{
    pbd_config config;

    CHECK_INT(PBD_CONFIG_OK, pbd_config_parse("color = blue\njunk\n#token = old\nserver_url=https://a\ntoken=new\n", &config));
    CHECK_STR("new", config.token);
}

static void reports_missing_or_invalid_values(void)
{
    pbd_config config;

    CHECK_INT(PBD_CONFIG_MISSING_URL, pbd_config_parse("", &config));
    CHECK_INT(PBD_CONFIG_MISSING_URL, pbd_config_parse("server_url = ///\ntoken = x\n", &config));
    CHECK_INT(PBD_CONFIG_INVALID_URL, pbd_config_parse("server_url = ftp://a\ntoken = x\n", &config));
    CHECK_INT(PBD_CONFIG_INVALID_URL, pbd_config_parse("server_url = pocketbook.example.com\ntoken = x\n", &config));
    CHECK_INT(PBD_CONFIG_MISSING_TOKEN, pbd_config_parse("server_url = https://a\ntoken =   \n", &config));
}

static void rejects_values_that_do_not_fit(void)
{
    pbd_config config;
    char text[PBD_TOKEN_MAX + 32];
    memset(text, 'x', sizeof(text));
    memcpy(text, "token=", 6);
    text[sizeof(text) - 1] = '\0';

    CHECK_INT(PBD_CONFIG_TOO_LONG, pbd_config_parse(text, &config));
}

static void accepts_a_token_of_maximum_length(void)
{
    pbd_config config;
    char text[PBD_TOKEN_MAX + 64] = "server_url=https://a\ntoken=";
    size_t prefix = strlen(text);
    memset(text + prefix, 'x', PBD_TOKEN_MAX - 1);
    text[prefix + PBD_TOKEN_MAX - 1] = '\0';

    CHECK_INT(PBD_CONFIG_OK, pbd_config_parse(text, &config));
    CHECK_INT(PBD_TOKEN_MAX - 1, (long long)strlen(config.token));
}

static void formats_a_file_that_parses_back(void)
{
    pbd_config config;
    pbd_config_parse("server_url=https://a.example\ntoken=pbd_abc\n", &config);
    char text[512];
    pbd_config reread;

    CHECK(pbd_config_format(&config, text, sizeof(text)) > 0);
    CHECK_INT(PBD_CONFIG_OK, pbd_config_parse(text, &reread));
    CHECK_STR(config.server_url, reread.server_url);
    CHECK_STR(config.token, reread.token);
    CHECK_INT(-1, pbd_config_format(&config, text, 10));
}

static void user_input_is_trimmed_then_validated(void)
{
    pbd_config config;
    pbd_config_parse("", &config);

    CHECK_INT(PBD_CONFIG_OK, pbd_config_set_url(&config, "  https://pocketbook.example.com/ "));
    CHECK_INT(PBD_CONFIG_OK, pbd_config_set_token(&config, " pbd_abc\n"));
    CHECK_INT(PBD_CONFIG_OK, pbd_config_validate(&config));
    CHECK_STR("https://pocketbook.example.com", config.server_url);
    CHECK_STR("pbd_abc", config.token);

    char too_long[PBD_URL_MAX + 1];
    memset(too_long, 'x', PBD_URL_MAX);
    too_long[PBD_URL_MAX] = '\0';
    CHECK_INT(PBD_CONFIG_TOO_LONG, pbd_config_set_url(&config, too_long));
    CHECK_STR("https://pocketbook.example.com", config.server_url);
}

static void builds_the_screen_url(void)
{
    pbd_config config;
    pbd_config_parse("server_url=https://a.example/\ntoken=x\n", &config);
    char url[64];

    const char *expected = "https://a.example/api/v1/screen?battery=87&charging=0";
    CHECK_INT((long long)strlen(expected), pbd_config_screen_url(&config, 87, 0, url, sizeof(url)));
    CHECK_STR(expected, url);
    pbd_config_screen_url(&config, 100, 2, url, sizeof(url));
    CHECK_STR("https://a.example/api/v1/screen?battery=100&charging=1", url);
    CHECK_INT(-1, pbd_config_screen_url(&config, 87, 0, url, 10));
}

static void omits_an_unknown_battery_level(void)
{
    pbd_config config;
    pbd_config_parse("server_url=https://a.example/\ntoken=x\n", &config);
    char url[64];

    pbd_config_screen_url(&config, -1, 0, url, sizeof(url));
    CHECK_STR("https://a.example/api/v1/screen", url);
    pbd_config_screen_url(&config, 101, 1, url, sizeof(url));
    CHECK_STR("https://a.example/api/v1/screen", url);
}

static void every_status_has_a_message(void)
{
    for (int status = PBD_CONFIG_OK; status <= PBD_CONFIG_TOO_LONG; status++) {
        CHECK(strlen(pbd_config_status_message((pbd_config_status)status)) > 0);
    }
    CHECK_STR("Configuration invalide.", pbd_config_status_message((pbd_config_status)99));
}

void test_config(void)
{
    parses_a_complete_file();
    parses_a_file_without_final_newline();
    ignores_unknown_keys_and_lines_without_value();
    reports_missing_or_invalid_values();
    rejects_values_that_do_not_fit();
    accepts_a_token_of_maximum_length();
    formats_a_file_that_parses_back();
    user_input_is_trimmed_then_validated();
    builds_the_screen_url();
    omits_an_unknown_battery_level();
    every_status_has_a_message();
}
