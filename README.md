# Remote procedure call (RPC) using php-amqplib and Rabbit MQ

Remote Procedure Call (RPC) is a design paradigm that allow two entities to communicate over a communication channel in a general request-response mechanism.

Simplest RPC implementation looks like Figure 1. In this case, the client (or caller) and the server (or callee) are separated by a physical network. The main components of the system are the client routine/program, the client stub, the server routine/program, the server stub, and the network routines. 

![simple_rpc](docs/simple_rpc.png)

RabbitMQ to build an RPC system: a client and a scalable RPC server. RPC client sends an RPC request to RPC Server and blocks until the answer is received:

![rpc](docs/rpc.png)

RPC will work like this:

 - When the Client starts up, it creates an anonymous exclusive callback queue.
 - For an RPC request, the Client sends a message with two properties: reply_to, which is set to the callback queue and correlation_id, which is set to a unique value for every request.
 - The request is sent to an rpc_queue queue.
 - The RPC worker (aka: server) is waiting for requests on that queue. When a request appears, it does the job and sends a message with the result back to the Client, using the queue from the reply_to field.
 - The client waits for data on the callback queue. When a message appears, it checks the correlation_id property. If it matches the value from the request it returns the response to the application.
 - As usual we start by establishing the connection, channel and declaring the queue.
 - We might want to run more than one server process. In order to spread the load equally over multiple servers we need to set the prefetch_count setting in $channel.basic_qos.
 - We use basic_consume to access the queue. Then we enter the while loop in which we wait for request messages, do the work and send the response back.

###Warning:

This package doesn't try to solve more complex (but important) problems, like:
 1) How should the client react if there are no servers running?
 2) Should a client have some kind of timeout for the RPC?
 3) If the server malfunctions and raises an exception, should it be forwarded to the client?
 4) Protecting against invalid incoming messages (eg checking bounds, type) before processing.

Although RPC is a pretty common pattern in computing, it's often criticised. The problems arise when a programmer is not aware whether a function call is local or if it's a slow RPC. Confusions like that result in an unpredictable system and adds unnecessary complexity to debugging. Instead of simplifying software, misused RPC can result in unmaintainable spaghetti code.

Bearing that in mind, consider the following advice:

1) Make sure it's obvious which function call is local and which is remote.
2) Document your system. Make the dependencies between components clear.
3) Handle error cases. How should the client react when the RPC server is down for a long time?
4) When in doubt avoid RPC. If you can, you should use an asynchronous pipeline - instead of RPC-like blocking, results are asynchronously pushed to a next computation stage.

##Common problems faced by PHP developers in consuming an AMQP message

####The memory problem

A normal PHP script serves a single request, then the script dies naturally as the execution of provided instructions is completed, and the allocated memory is freed. In a PHP consumer we have to execute a long running PHP process: this will cause us to face a memory problem, because PHP is not properly designed to achieve this goal, and this will cause trouble for sure if we don’t pay attention to the script code.

Normally, you don’t notice any memory issue due to fact that the script runs for few seconds; also, in a long running PHP process, if you try and run a simple script for few minutes it could not use any additional memory at all, but if you use some framework, an ORM or you are in a complex application with many dependencies, the application will eventually allocate some memory (e.g. for caching results), and you could even notice a progressive increase of the memory used by the PHP process due to some memory leaks.

That’s not a problem at all in a normal case, where the script ends and the memory is freed normally, but the case is not that obvious for a long running process like the one required by a PHP consumer. It will break, leak and, in some cases, corrupt the memory, and this will make the process crash or, more drastically, fail to run some instructions, as the opcode cache gets changed while the process is running.

If you are interested in more accurate details and information, I suggest reading this article: it’s pretty old, but explains this issue very well.

####Multiple consumers

For a PHP consumer it’s very hard to process multiple messages at the same time, because PHP runs in a single thread, and a proper async/await interface is not well supported or mature. Again, PHP is not designed for this scope and this could be a limit in some circumstances: scaling is really hard.

Running multiple PHP consumers is not a good idea as well: given a framework-based application, it could use a huge amounts of RAM, easily GBs. If during the “waiting” time there are no messages, it’s going to be a serious waste of memory that could be used instead for some other resources or requests.

####Updating the codebase

Another common problem with PHP consumers is when we need to update the codebase: if we deploy something that changes the code and some service used by the consumer, we can easily break the integrity of the entire long running process execution.

We have to shutdown all consumers and sometimes we must force kill processes: they could hang due to memory corruption, so we have to start them again with a script (obviously) that will trigger automatically during the deploy or within the pipeline.

####The network problem

When we work with a network based service, we have to expect failures and we must have some reconnection policies. A common problem when we have a long running PHP process is a broken pipe (in a very popular bundle this is still an issue due to PHP nature, see here) because we can’t use a feature that is exactly made for this sort of issues resolution, the Heartbeat. In a AMPQ server connection, normally we have to implement a sort “ping” mechanism, this is a mention of the [official RabbitMQ documentation:](https://www.rabbitmq.com/heartbeats.html)

Network can fail in many ways, sometimes pretty subtle (e.g. high ratio packet loss). Disrupted TCP connections take a moderately long time (about 11 minutes with default configuration on Linux, for example) to be detected by the operating system. AMQP 0-9-1 offers a heartbeat feature to ensure that the application layer promptly finds out about disrupted connections (and also completely unresponsive peers). Heartbeats also defend against certain network equipment which may terminate “idle” TCP connections.

This is something that is still not possible in PHP and obviously cause troubles in our PHP consumer.

####Some solution

We ran this configuration for some time and we experienced ALL of these problems randomly during the normal application flows. We decided to find a good solution and we ended up with an external CLI command processor written in another language and designed for the scope.

The pros of having an external consumer like this is that we haven’t to care of the supervision of the process nor worries in case of changes on the codebase.

[Example go consumer](https://github.com/corvus-ch/rabbitmq-cli-consumer) 

[Full rabbit mq official example](https://www.rabbitmq.com/tutorials/tutorial-six-php.html)

## Swagger ##

[localhost:8081/docs/index.html](http://localhost:8081/docs/index.html)