--TEST--
Attributes (Annotations) - PHP 8+ metadata syntax
--SKIPIF--
--FILE--
<?php
// Define attribute classes
#[Attribute(Attribute::TARGET_CLASS)]
class Route {
    public string $path;
    public array $methods;
    
    public function __construct(string $path, array $methods = ['GET']) {
        $this->path = $path;
        $this->methods = $methods;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class Cache {
    public int $ttl;
    
    public function __construct(int $ttl = 3600) {
        $this->ttl = $ttl;
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column {
    public string $name;
    public string $type;
    
    public function __construct(string $name, string $type = 'string') {
        $this->name = $name;
        $this->type = $type;
    }
}

// Use attributes
#[Route('/api/users', ['GET', 'POST'])]
class UserController {
    #[Column('id', 'int')]
    private int $id;
    
    #[Column('name', 'string')]
    private string $name;
    
    #[Cache(ttl: 1800)]
    public function getUsers() {
        return "Getting users";
    }
    
    #[Cache(ttl: 3600)]
    public function getUser($id) {
        return "Getting user: " . $id;
    }
}

#[Route('/api/posts')]
class PostController {
    #[Cache]
    public function getPosts() {
        return "Getting posts";
    }
}

function main() {
    // Test class attributes
    $userController = new ReflectionClass(UserController::class);
    $attributes = $userController->getAttributes();
    var_dump(count($attributes));
    
    $routeAttr = $attributes[0]->newInstance();
    var_dump($routeAttr->path);
    var_dump($routeAttr->methods);
    
    // Test method attributes
    $getMethod = $userController->getMethod('getUsers');
    $methodAttrs = $getMethod->getAttributes();
    var_dump(count($methodAttrs));
    
    $cacheAttr = $methodAttrs[0]->newInstance();
    var_dump($cacheAttr->ttl);
    
    // Test property attributes
    $idProp = $userController->getProperty('id');
    $propAttrs = $idProp->getAttributes();
    var_dump(count($propAttrs));
    
    $columnAttr = $propAttrs[0]->newInstance();
    var_dump($columnAttr->name);
    var_dump($columnAttr->type);
    
    // Test another class
    $postController = new ReflectionClass(PostController::class);
    $postAttrs = $postController->getAttributes();
    var_dump(count($postAttrs));
    
    $postRoute = $postAttrs[0]->newInstance();
    var_dump($postRoute->path);
}
?>
--EXPECT--
int(1)
string(10) "/api/users"
array(2) {
  [0]=>
  string(3) "GET"
  [1]=>
  string(4) "POST"
}
int(1)
int(1800)
int(1)
string(2) "id"
string(3) "int"
int(1)
string(10) "/api/posts"
