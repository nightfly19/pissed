(def *input* (foreign-global "buffered_stdin"))

(def concat (lambda (a b) (foreign "implode" (1 2 3) )))
