#include "client.h"
#include "config.h"

#include <curl/curl.h>
#include <inkview.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <time.h>

#define CONFIG_FILE CONFIGPATH "/pocketbook-dashboard.cfg"
#define LOG_FILE USERDATA "/pocketbook-dashboard.log"
#define SCREEN_FILE TEMPDIR "/pocketbook-dashboard.png"

#define DEFAULT_REFRESH_SECONDS 300
#define RETRY_SECONDS 60
#define LOG_MAX_BYTES 65536
#define BANNER_HEIGHT 110
#define TEXT_MARGIN 60

enum {
    MENU_REFRESH = 1,
    MENU_SERVER,
    MENU_TOKEN,
    MENU_EXIT,
};

static pbd_config config;
static pbd_config_status config_status = PBD_CONFIG_MISSING_URL;
static ibitmap *screen;
static ifont *title_font;
static ifont *text_font;
static char keyboard_buffer[PBD_URL_MAX > PBD_TOKEN_MAX ? PBD_URL_MAX : PBD_TOKEN_MAX];

static imenu menu[] = {
    { ITEM_HEADER, 0, "PocketBook Dashboard", NULL },
    { ITEM_ACTIVE, MENU_REFRESH, "Rafraîchir maintenant", NULL },
    { ITEM_ACTIVE, MENU_SERVER, "Adresse du serveur…", NULL },
    { ITEM_ACTIVE, MENU_TOKEN, "Clé d'accès…", NULL },
    { ITEM_SEPARATOR, 0, NULL, NULL },
    { ITEM_ACTIVE, MENU_EXIT, "Quitter", NULL },
    { 0, 0, NULL, NULL },
};

static void refresh(void);

/* Journal consultable par USB, pour diagnostiquer une liseuse qui n'affiche rien. */
static void log_line(const char *format, ...)
{
    struct stat info;
    FILE *file = fopen(LOG_FILE, stat(LOG_FILE, &info) == 0 && info.st_size > LOG_MAX_BYTES ? "w" : "a");
    if (file == NULL) {
        return;
    }

    char timestamp[32];
    time_t now = time(NULL);
    strftime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S", localtime(&now));
    fprintf(file, "%s ", timestamp);

    va_list arguments;
    va_start(arguments, format);
    vfprintf(file, format, arguments);
    va_end(arguments);

    fputc('\n', file);
    fclose(file);
}

static void load_config(void)
{
    char text[1024] = "";
    FILE *file = fopen(CONFIG_FILE, "r");
    if (file != NULL) {
        size_t length = fread(text, 1, sizeof(text) - 1, file);
        text[length] = '\0';
        fclose(file);
    }
    config_status = pbd_config_parse(text, &config);
}

static void save_config(void)
{
    char text[1024];
    FILE *file = fopen(CONFIG_FILE, "w");
    if (file == NULL || pbd_config_format(&config, text, sizeof(text)) < 0 || fputs(text, file) == EOF) {
        log_line("Impossible d'enregistrer %s", CONFIG_FILE);
    }
    if (file != NULL) {
        fclose(file);
    }
}

static void show_message(const char *title, const char *text)
{
    int width = ScreenWidth() - 2 * TEXT_MARGIN;
    ClearScreen();
    SetFont(title_font, BLACK);
    DrawTextRect(TEXT_MARGIN, ScreenHeight() / 3, width, 120, title, ALIGN_CENTER | VALIGN_MIDDLE);
    SetFont(text_font, BLACK);
    DrawTextRect(TEXT_MARGIN, ScreenHeight() / 3 + 160, width, ScreenHeight() / 2, text, ALIGN_CENTER | VALIGN_TOP);
    FullUpdate();
}

static void show_configuration_help(void)
{
    char text[1024];
    snprintf(text, sizeof(text),
        "%s\n\n"
        "Appuyez sur la touche Menu (ou restez appuyé sur l'écran) pour saisir l'adresse du serveur "
        "et la clé d'accès, ou créez le fichier system/config/pocketbook-dashboard.cfg :\n\n"
        "server_url = https://…\n"
        "token = pbd_…",
        pbd_config_status_message(config_status));
    show_message("Configuration requise", text);
}

static void draw_screen(void)
{
    if (screen->width == ScreenWidth() && screen->height == ScreenHeight()) {
        DrawBitmap(0, 0, screen);
    } else {
        StretchBitmap(0, 0, ScreenWidth(), ScreenHeight(), screen, 0);
    }
}

static void redraw(void)
{
    if (screen == NULL) {
        show_configuration_help();
        return;
    }
    draw_screen();
    FullUpdate();
}

/* Signale une erreur sans effacer le dernier écran reçu, qui reste plus utile qu'une page blanche. */
static void show_error(const char *error)
{
    log_line("Erreur : %s", error);

    char text[PBD_ERROR_MAX + 64];
    time_t retry = time(NULL) + RETRY_SECONDS;
    char retry_time[8];
    strftime(retry_time, sizeof(retry_time), "%H:%M", localtime(&retry));
    snprintf(text, sizeof(text), "%s\nNouvel essai à %s.", error, retry_time);

    if (screen == NULL) {
        show_message("Écran indisponible", text);
        return;
    }

    int top = ScreenHeight() - BANNER_HEIGHT;
    draw_screen();
    FillArea(0, top, ScreenWidth(), BANNER_HEIGHT, WHITE);
    DrawLine(0, top, ScreenWidth(), top, BLACK);
    SetFont(text_font, BLACK);
    DrawTextRect(TEXT_MARGIN, top, ScreenWidth() - 2 * TEXT_MARGIN, BANNER_HEIGHT, text, ALIGN_CENTER | VALIGN_MIDDLE | DOTS);
    FullUpdate();
}

static void schedule_refresh(int seconds)
{
    SetHardTimer("pbd_refresh", refresh, seconds * 1000);
}

static void refresh(void)
{
    ClearTimer(refresh);

    if (config_status != PBD_CONFIG_OK) {
        show_configuration_help();
        return;
    }

    int network = NetConnect(NULL);
    if (network != NET_OK) {
        log_line("Wi-Fi indisponible (code %d)", network);
        show_error("Wi-Fi indisponible.");
        schedule_refresh(RETRY_SECONDS);
        return;
    }

    pbd_fetch_result result;
    int fetched = pbd_fetch_screen(&config, SCREEN_FILE, &result);

    // Sur batterie, le Wi-Fi est coupé entre deux écrans pour préserver l'autonomie
    if (!IsCharging()) {
        NetDisconnect();
    }

    if (fetched != 0) {
        show_error(result.error);
        schedule_refresh(RETRY_SECONDS);
        return;
    }

    ibitmap *received = LoadPNG(SCREEN_FILE, 0);
    if (received == NULL) {
        show_error("Image reçue illisible.");
        schedule_refresh(RETRY_SECONDS);
        return;
    }

    free(screen);
    screen = received;
    redraw();
    schedule_refresh(result.refresh_seconds > 0 ? result.refresh_seconds : DEFAULT_REFRESH_SECONDS);
}

static void on_value_entered(char *text, pbd_config_status (*set)(pbd_config *, const char *))
{
    if (text == NULL) {
        redraw();
        return;
    }

    if (set(&config, text) == PBD_CONFIG_TOO_LONG) {
        show_error("Valeur trop longue.");
        return;
    }
    config_status = pbd_config_validate(&config);
    save_config();
    refresh();
}

static void on_server_entered(char *text)
{
    on_value_entered(text, pbd_config_set_url);
}

static void on_token_entered(char *text)
{
    on_value_entered(text, pbd_config_set_token);
}

static void open_keyboard(const char *title, const char *value, int flags, iv_keyboardhandler handler)
{
    snprintf(keyboard_buffer, sizeof(keyboard_buffer), "%s", value);
    OpenKeyboard(title, keyboard_buffer, (int)sizeof(keyboard_buffer) - 1, flags, handler);
}

static void on_menu(int index)
{
    switch (index) {
    case MENU_REFRESH:
        refresh();
        break;
    case MENU_SERVER:
        open_keyboard("Adresse du serveur", config.server_url[0] != '\0' ? config.server_url : "https://", KBD_URL, on_server_entered);
        break;
    case MENU_TOKEN:
        open_keyboard("Clé d'accès", config.token, KBD_NORMAL, on_token_entered);
        break;
    case MENU_EXIT:
        CloseApp();
        break;
    default:
        redraw();
        break;
    }
}

static void open_menu(void)
{
    OpenMenu(menu, MENU_REFRESH, ScreenWidth() / 4, ScreenHeight() / 4, on_menu);
}

static int on_key(int key)
{
    switch (key) {
    case IV_KEY_BACK:
    case IV_KEY_HOME:
        CloseApp();
        return 1;
    case IV_KEY_MENU:
    case IV_KEY_OK:
        open_menu();
        return 1;
    case IV_KEY_PREV:
    case IV_KEY_NEXT:
        refresh();
        return 1;
    default:
        return 0;
    }
}

static void start(void)
{
    title_font = OpenFont(DEFAULTFONTB, 56, 1);
    text_font = OpenFont(DEFAULTFONT, 36, 1);
    // L'écran du serveur occupe toute la surface : pas de barre d'état système
    SetPanelType(PANEL_DISABLED);
    // L'application doit continuer de tourner : seule la veille (réveillée par le minuteur) est autorisée
    SetAutoPowerOff(false);
    load_config();
    log_line("Démarrage (%s)", pbd_config_status_message(config_status));
    refresh();
}

static void stop(void)
{
    ClearTimer(refresh);
    free(screen);
    screen = NULL;
    CloseFont(title_font);
    CloseFont(text_font);
    SetAutoPowerOff(true);
    curl_global_cleanup();
}

static int on_event(int type, int par1, int par2)
{
    (void)par2;

    switch (type) {
    case EVT_INIT:
        start();
        return 1;
    case EVT_SHOW:
        redraw();
        return 1;
    case EVT_KEYPRESS:
        return on_key(par1);
    case EVT_POINTERUP:
        refresh();
        return 1;
    case EVT_POINTERLONG:
        open_menu();
        return 1;
    case EVT_EXIT:
        stop();
        return 1;
    default:
        return 0;
    }
}

int main(void)
{
    curl_global_init(CURL_GLOBAL_DEFAULT);
    InkViewMain(on_event);
    return 0;
}
