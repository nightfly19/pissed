(def *input* (foreign-global "buffered_stdin"))
(def *output* (lambda (data) (foreign-print data)))

(def concat (lambda (a b) (foreign-concat a b)))

(def print (lambda (text)
             (*output* text)))

(def write-line (lambda (text)
                  (print text)
                  (print "\n")))

(def cadr (lambda (cell) (car (cdr cell))))
(def cdar (lambda (cell) (cdr (car cell))))

