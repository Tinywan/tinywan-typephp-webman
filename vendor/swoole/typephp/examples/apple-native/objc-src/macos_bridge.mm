#include <AppKit/AppKit.h>
#include <phpx.h>

static NSString *to_ns_string(const php::Str &value)
{
    NSString *result = [[NSString alloc] initWithBytes:value.data()
                                                length:value.length()
                                              encoding:NSUTF8StringEncoding];
    return result == nil ? @"" : result;
}

@interface TypePHPAppKitBridge : NSObject <NSWindowDelegate>

@property(nonatomic, strong) NSWindow *window;
@property(nonatomic, strong) NSMutableDictionary<NSNumber *, NSControl *> *controls;
@property(nonatomic) NSInteger nextControlId;
@property(nonatomic) NSInteger pendingControlId;
@property(nonatomic) BOOL running;

- (void)controlActivated:(NSControl *)sender;

@end

@implementation TypePHPAppKitBridge

- (instancetype)init
{
    self = [super init];
    if (self != nil) {
        self.controls = [[NSMutableDictionary alloc] init];
        self.nextControlId = 1;
    }
    return self;
}

- (void)controlActivated:(NSControl *)sender
{
    self.pendingControlId = sender.tag;
}

- (void)windowWillClose:(NSNotification *)notification
{
    (void) notification;
    self.running = NO;
}

@end

static __strong TypePHPAppKitBridge *bridge = nil;

static void install_application_menu(NSString *applicationName)
{
    NSMenu *menuBar = [[NSMenu alloc] init];
    NSMenuItem *applicationMenuItem = [[NSMenuItem alloc] init];
    [menuBar addItem:applicationMenuItem];
    NSApp.mainMenu = menuBar;

    NSMenu *applicationMenu = [[NSMenu alloc] init];
    NSString *quitTitle = [@"Quit " stringByAppendingString:applicationName];
    NSMenuItem *quitItem = [[NSMenuItem alloc] initWithTitle:quitTitle
                                                    action:@selector(terminate:)
                                             keyEquivalent:@"q"];
    [applicationMenu addItem:quitItem];
    applicationMenuItem.submenu = applicationMenu;
}

static NSInteger store_control(NSControl *control)
{
    const NSInteger controlId = bridge.nextControlId++;
    control.tag = controlId;
    bridge.controls[@(controlId)] = control;
    return controlId;
}

void php_ui_app_init(php::Str applicationName)
{
    @autoreleasepool {
        NSApplication *application = NSApplication.sharedApplication;
        application.activationPolicy = NSApplicationActivationPolicyRegular;
        bridge = [[TypePHPAppKitBridge alloc] init];
        install_application_menu(to_ns_string(applicationName));
        [application finishLaunching];
    }
}

void php_ui_create_window(php::Str title, php::Int width, php::Int height)
{
    @autoreleasepool {
        const NSRect frame = NSMakeRect(0, 0, static_cast<CGFloat>(width), static_cast<CGFloat>(height));
        const NSWindowStyleMask style = NSWindowStyleMaskTitled |
                                        NSWindowStyleMaskClosable |
                                        NSWindowStyleMaskMiniaturizable |
                                        NSWindowStyleMaskResizable;
        bridge.window = [[NSWindow alloc] initWithContentRect:frame
                                                   styleMask:style
                                                     backing:NSBackingStoreBuffered
                                                       defer:NO];
        bridge.window.releasedWhenClosed = NO;
        bridge.window.delegate = bridge;
        bridge.window.title = to_ns_string(title);
        bridge.window.minSize = NSMakeSize(480, 300);
        [bridge.window center];
    }
}

php::Int php_ui_add_label(
    php::Str text,
    php::Int x,
    php::Int y,
    php::Int width,
    php::Int height,
    php::Int fontSize,
    php::Bool bold)
{
    @autoreleasepool {
        NSTextField *label = [NSTextField wrappingLabelWithString:to_ns_string(text)];
        label.frame = NSMakeRect(
            static_cast<CGFloat>(x),
            static_cast<CGFloat>(y),
            static_cast<CGFloat>(width),
            static_cast<CGFloat>(height));
        label.font = bold
                         ? [NSFont systemFontOfSize:static_cast<CGFloat>(fontSize)
                                            weight:NSFontWeightSemibold]
                         : [NSFont systemFontOfSize:static_cast<CGFloat>(fontSize)];
        label.alignment = NSTextAlignmentCenter;
        label.autoresizingMask = NSViewWidthSizable | NSViewMinYMargin;
        [bridge.window.contentView addSubview:label];
        return static_cast<php::Int>(store_control(label));
    }
}

php::Int php_ui_add_button(
    php::Str title,
    php::Int x,
    php::Int y,
    php::Int width,
    php::Int height,
    php::Int style)
{
    @autoreleasepool {
        NSButton *button = [NSButton buttonWithTitle:to_ns_string(title)
                                              target:bridge
                                              action:@selector(controlActivated:)];
        button.frame = NSMakeRect(
            static_cast<CGFloat>(x),
            static_cast<CGFloat>(y),
            static_cast<CGFloat>(width),
            static_cast<CGFloat>(height));
        // macOS 11+: keep the automatic button style. Forcing the legacy
        // NSBezelStyleRounded disables the accent fill that the primary button
        // needs behind its white title. The automatic style also makes
        // bezelColor render as a proper filled button.
        button.controlSize = NSControlSizeLarge;
        button.font = [NSFont systemFontOfSize:14 weight:NSFontWeightSemibold];
        if (style == 1) {
            button.bezelColor = NSColor.controlAccentColor;
            button.contentTintColor = NSColor.whiteColor;
            button.keyEquivalent = @"\r";
        }
        button.autoresizingMask = NSViewMinXMargin | NSViewMaxXMargin | NSViewMinYMargin;
        [bridge.window.contentView addSubview:button];
        return static_cast<php::Int>(store_control(button));
    }
}

void php_ui_set_control_text(php::Int controlId, php::Str text)
{
    @autoreleasepool {
        NSControl *control = bridge.controls[@(static_cast<NSInteger>(controlId))];
        if (control == nil) {
            return;
        }
        NSString *value = to_ns_string(text);
        if ([control isKindOfClass:NSButton.class]) {
            // Update the title through the NSButton property. System-style
            // buttons (macOS 11+) draw from their attributed title; assigning
            // only the raw cell stringValue can leave the button blank.
            ((NSButton *)control).title = value;
        } else {
            control.stringValue = value;
        }
    }
}

void php_ui_show_window()
{
    @autoreleasepool {
        [bridge.window makeKeyAndOrderFront:nil];
        bridge.running = YES;
        [NSApp activateIgnoringOtherApps:YES];
    }
}

php::Int php_ui_next_event()
{
    while (bridge.running) {
        @autoreleasepool {
            NSEvent *event = [NSApp nextEventMatchingMask:NSEventMaskAny
                                                untilDate:NSDate.distantFuture
                                                   inMode:NSDefaultRunLoopMode
                                                  dequeue:YES];
            if (event != nil) {
                [NSApp sendEvent:event];
                [NSApp updateWindows];
            }

            if (bridge.pendingControlId != 0) {
                const NSInteger controlId = bridge.pendingControlId;
                bridge.pendingControlId = 0;
                return static_cast<php::Int>(controlId);
            }
        }
    }
    return -1;
}
