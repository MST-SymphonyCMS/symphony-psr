##### Symphony CMS

# PSR2 Compliance Project

This is a personal project of [@designermonkey](http://github.com/designermonkey) to see whether what Symphony CMS currently has available as a codebase can be converted into a fully PSR2 standards compliant application. This is also an excercise to better understand PHP in general, and the Symphony codebase as it is.

This is not part of a community project, although I will ask for help understanding how Symphony currently works in some places.

## Why bother?

When the community started to discuss rewriting Symphony using a popular framework, it struck me that like so many other projects, we would just be re-inventing the wheel from a content management perspective (not a code perspective) and starting from scratch; We would have to ask the same questions that we have already tackled when writing the current codebase we have today. I went along with the consensus as the community lead, until I had a realisation that nothing was happening fast. Discussions were stagnating, people were leaving, and I couldn't get anyone to agree; So I left.

Granted, and I agree with the community, a rewrite would instantly address the lack of testability, the re-invention of the wheel (Email work for example, when we could have used SwiftMailer), but without a basis to start from there is no structure, and too much time has already been spent on discussing structure with no actual plan.

This plan is born of my lack of patience any more with these discussions; I simply do not have the time to discus ideas and wait weeks for consensus which rarely comes. This shouldn't be taken as a negative point to anyone in the community, I have great respect for all of them, many of whom I call friends.

We have a structure, we have a codebase, I just want to get on with fixing it up to the modern standards.

I may well find in the end, that I have rewritten 80% of the project, or may not, but I want a little fun trying. I like to torture myself that way. Anyway, it's about the journey to me, and this will be a journey.


## Structure

Many times has the lack of an MVC approach been highlighted, but if we actually look at the structure of what we have, we more or less already do have a similar structure; It just needs manipulating a little.

Laravel, as an example, uses `Repositories` to abstract the data away from a tight coupling of `Conteollers` and `Models`. One could call this an MRVC method. This (apparently, as I have only lightly read about it) allows for testability to be injected instead of a standard `Model`, a mock object of data can be used instead.

Along those lines, Symphony can be aligned with the same style quite easily. We have `Managers` instead of `Repositories`, the only issue being that some of the code we would expect a `Manager` to process is in the `Object` and sometimes in the content page too. Tightening this all up and moving concerns to their correct expected place will prove that we don't need to re-invent the wheel, as we actually already are doing it a good solid way. Think of it as starting out at a gym, I want to tighten the Symphony muscles, and lose the shabby flabby bits.


##Schedule of Works

I have moved this to a separate repository to allow the use of issues to maintain work to be done. The issues will initially be opened in the order I think they need to be completed, prefixed with an order number, but this will change over time.