package main

import "fmt"

func main() {
	const rounds = 100000000

	x := 1.0
	pi := 1.0
	stop := float64(rounds + 2)

	for i := 2.0; i <= stop; i++ {
		x = -x
		pi += x / (2.0*i - 1.0)
	}

	pi *= 4.0
	fmt.Println(pi)
}
