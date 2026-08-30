public class Hello {
    private String name;
    private int age;

    public Hello(String name, int age) {
        this.name = name;
        this.age = age;
    }

    public String greet(String greeting) {
        return greeting + ", I'm " + name + ", " + age + " years old";
    }

    public String getName() {
        return name;
    }

    public int getAge() {
        return age;
    }
}
