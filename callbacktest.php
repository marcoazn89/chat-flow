class A
{
    protected $fn = null;

    function register(\Closure $call)
    {
        $this->fn = $call;
    }

    function go($input)
    {
        $fn = $this->fn;
        return $fn($input);
    }
}

function output($input)
{
    echo $input;
}

$a = new A;

$a->register(function ($input)  {
    return output($input);
});

$a->go('hello');
die;
