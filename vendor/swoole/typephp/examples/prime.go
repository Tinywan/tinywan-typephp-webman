package main

import (
	"strconv"
	"time"
)

func NthPrime(n int) int {

	var (
		i int = 2
		j int = 1
	)

	for {
		j = j + 1;
		if j > i/j {
			n--
			if n == 0 {
				break
			}
			j = 1
		}
		if i%j == 0 {
			i++;
			j = 1;
		}
	}
	return i
}

func main() {
	s := time.Now().UnixNano() / 1e6
	n := 300000
	result := NthPrime(n)
	e := time.Now().UnixNano() / 1e6
	time := e - s
	println("第" + strconv.Itoa(n) + "个素数的值是:" + strconv.Itoa(result) + " 耗时" + strconv.Itoa(int(time)) + "毫秒")
}
//第300000个素数的值是:4256233 耗时10417毫秒