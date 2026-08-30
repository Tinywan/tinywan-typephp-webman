class ProtocolObject:
    def __init__(self):
        self.name = "initial"
        self.values = [10, 20, 30]

    def greet(self, prefix, suffix="!"):
        return f"{prefix} {self.name}{suffix}"

    def __call__(self, left, right=0):
        return left + right

def protocol_object():
    return ProtocolObject()


def callback_with_kwargs(callback):
    return callback("left", right=7)
