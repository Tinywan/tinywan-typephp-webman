#include <UIKit/UIKit.h>
#include <phpx.h>

void php_typephp_application_did_launch();
void php_typephp_application_control_activated(php::Int controlId);

static NSString *to_ns_string(const php::Str &value)
{
    NSString *result = [[NSString alloc] initWithBytes:value.data()
                                                length:value.length()
                                              encoding:NSUTF8StringEncoding];
    return result == nil ? @"" : result;
}

@interface TypePHPUIKitBridge : NSObject <UIApplicationDelegate>

@property(nonatomic, strong) UIWindow *window;
@property(nonatomic, strong) UIViewController *rootViewController;
@property(nonatomic, strong) NSMutableDictionary<NSNumber *, UIView *> *controls;
@property(nonatomic) NSInteger nextControlId;
@property(nonatomic) CGFloat logicalWidth;
@property(nonatomic) CGFloat logicalHeight;

- (void)controlActivated:(UIControl *)sender;

@end

static __strong TypePHPUIKitBridge *bridge = nil;
static __strong NSString *applicationName = nil;

@implementation TypePHPUIKitBridge

- (instancetype)init
{
    self = [super init];
    if (self != nil) {
        self.controls = [[NSMutableDictionary alloc] init];
        self.nextControlId = 1;
    }
    return self;
}

- (BOOL)application:(UIApplication *)application
    didFinishLaunchingWithOptions:(NSDictionary<UIApplicationLaunchOptionsKey, id> *)launchOptions
{
    (void) application;
    (void) launchOptions;
    bridge = self;
    php_typephp_application_did_launch();
    return YES;
}

- (void)controlActivated:(UIControl *)sender
{
    php_typephp_application_control_activated(static_cast<php::Int>(sender.tag));
}

@end


static CGRect logical_rect(php::Int x, php::Int y, php::Int width, php::Int height)
{
    const CGRect bounds = bridge.rootViewController.view.bounds;
    const CGFloat scaleX = CGRectGetWidth(bounds) / bridge.logicalWidth;
    const CGFloat scaleY = CGRectGetHeight(bounds) / bridge.logicalHeight;
    const CGFloat top = bridge.logicalHeight - static_cast<CGFloat>(y + height);
    return CGRectMake(
        static_cast<CGFloat>(x) * scaleX,
        top * scaleY,
        static_cast<CGFloat>(width) * scaleX,
        static_cast<CGFloat>(height) * scaleY);
}

static php::Int store_control(UIView *control)
{
    const NSInteger controlId = bridge.nextControlId++;
    control.tag = controlId;
    bridge.controls[@(controlId)] = control;
    return static_cast<php::Int>(controlId);
}

void php_ui_app_run(php::Str name)
{
    @autoreleasepool {
        applicationName = to_ns_string(name);
        UIApplicationMain(0, nullptr, nil, NSStringFromClass(TypePHPUIKitBridge.class));
    }
}

void php_ui_create_window(php::Str title, php::Int width, php::Int height)
{
    @autoreleasepool {
        (void) title;
        bridge.logicalWidth = static_cast<CGFloat>(width);
        bridge.logicalHeight = static_cast<CGFloat>(height);
        bridge.window = [[UIWindow alloc] initWithFrame:UIScreen.mainScreen.bounds];
        bridge.rootViewController = [[UIViewController alloc] init];
        bridge.rootViewController.title = applicationName;
        bridge.rootViewController.view.backgroundColor = UIColor.systemBackgroundColor;
        bridge.window.rootViewController = bridge.rootViewController;
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
        UILabel *label = [[UILabel alloc] initWithFrame:logical_rect(x, y, width, height)];
        label.text = to_ns_string(text);
        label.font = bold
                         ? [UIFont systemFontOfSize:static_cast<CGFloat>(fontSize)
                                            weight:UIFontWeightSemibold]
                         : [UIFont systemFontOfSize:static_cast<CGFloat>(fontSize)];
        label.textColor = bold ? UIColor.labelColor : UIColor.secondaryLabelColor;
        label.textAlignment = NSTextAlignmentCenter;
        label.numberOfLines = 0;
        label.adjustsFontSizeToFitWidth = YES;
        label.minimumScaleFactor = 0.75;
        [bridge.rootViewController.view addSubview:label];
        return store_control(label);
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
        UIButton *button = [UIButton buttonWithType:UIButtonTypeSystem];
        button.frame = logical_rect(x, y, width, height);
        UIButtonConfiguration *configuration;
        if (style == 1) {
            configuration = [UIButtonConfiguration filledButtonConfiguration];
            configuration.baseBackgroundColor = UIColor.systemBlueColor;
            configuration.baseForegroundColor = UIColor.whiteColor;
        } else if (style == 2) {
            configuration = [UIButtonConfiguration tintedButtonConfiguration];
            configuration.baseBackgroundColor = UIColor.systemBlueColor;
        } else {
            configuration = [UIButtonConfiguration grayButtonConfiguration];
        }
        configuration.title = to_ns_string(title);
        configuration.cornerStyle = UIButtonConfigurationCornerStyleLarge;
        configuration.contentInsets = NSDirectionalEdgeInsetsMake(12, 18, 12, 18);
        button.configuration = configuration;
        button.titleLabel.font = [UIFont systemFontOfSize:17 weight:UIFontWeightSemibold];
        button.titleLabel.adjustsFontSizeToFitWidth = YES;
        button.titleLabel.minimumScaleFactor = 0.75;
        button.titleLabel.lineBreakMode = NSLineBreakByTruncatingTail;
        [button addTarget:bridge
                   action:@selector(controlActivated:)
         forControlEvents:UIControlEventTouchUpInside];
        [bridge.rootViewController.view addSubview:button];
        return store_control(button);
    }
}

void php_ui_set_control_text(php::Int controlId, php::Str text)
{
    @autoreleasepool {
        UIView *control = bridge.controls[@(static_cast<NSInteger>(controlId))];
        NSString *value = to_ns_string(text);
        if ([control isKindOfClass:UILabel.class]) {
            ((UILabel *) control).text = value;
        } else if ([control isKindOfClass:UIButton.class]) {
            UIButton *button = (UIButton *) control;
            UIButtonConfiguration *configuration = button.configuration;
            if (configuration != nil) {
                configuration.title = value;
                button.configuration = configuration;
            } else {
                [button setTitle:value forState:UIControlStateNormal];
            }
            button.accessibilityLabel = value;
        }
    }
}

void php_ui_show_window()
{
    @autoreleasepool {
        [bridge.window makeKeyAndVisible];
    }
}
