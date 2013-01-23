(def *input* (foreign-global "buffered_stdin"))
(def *output* (foreign "fopen" "php://stdout" "w"))

(def concat (lambda (a b) (foreign-concat a b)))

(def print (lambda (text)
             (foreign "fwrite" *output* text)
             ()))

(def write-line (lambda (text)
                  (print text)
                  (print "\n")))




