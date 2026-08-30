--TEST--
Property with nullable type
--FILE--
<?php
class Router { function dispatch() { echo "router\n"; } }
class SessionManager { function start() { echo "SessionManager\n"; } }
class CsrfGuard { function validate() { echo "CsrfGuard\n"; } }
class Handler {
    private string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
    function handleError() {
        echo $this->name . "\n";
    }
}
class SpaRenderer { function render() { echo "SpaRenderer\n"; } }

class App {
    public function __construct(
        private Router $router,
        private SessionManager $sessions = new SessionManager(),
        private CsrfGuard $csrf = new CsrfGuard(),
        private Handler $errors = new Handler('Handler'),
        private SpaRenderer $spa = new SpaRenderer(),
    ) {}

    public function run() {
        $this->router->dispatch();
        $this->sessions->start();
        $this->csrf->validate();
        $this->errors->handleError();
        $this->spa->render();
    }
}

function main()
{
    $app = new App(new Router());
    $app->run();
}
?>
--EXPECT--
router
SessionManager
CsrfGuard
Handler
SpaRenderer