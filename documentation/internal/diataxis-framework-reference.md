# Diátaxis: A Systematic Approach to Technical Documentation

> **Source:** [diataxis.fr](https://diataxis.fr/) — Created by Daniele Procida
> **Etymology:** From Ancient Greek δῐᾰ́τᾰξῐς — *dia* ("across") + *taxis* ("arrangement")

---

## What Is Diátaxis?

Diátaxis is a framework for thinking about, creating, and organising technical documentation. It is built on one core premise: **good documentation serves the needs of its users**, and those needs fall into exactly four distinct categories.

The framework addresses three interconnected problems that documentation teams face: what to write (content), how to write it (style), and how to organise it (architecture). It does so by identifying four modes of documentation — **Tutorials, How-To Guides, Reference, and Explanation** — each of which serves a different user need and demands a different writing approach. These four modes must be kept distinct from one another; blurring the boundaries between them is the root cause of most documentation problems.

Diátaxis is deliberately lightweight. It is not a specification, a standard, or a toolchain. It is a set of principles that can be applied iteratively to any body of documentation at any stage of maturity. Adopters include Canonical/Ubuntu, Cloudflare, Gatsby, Python, Django, and many others.

---

## The Two Axes

Diátaxis arranges its four documentation types on a two-dimensional map defined by two axes. These axes describe the fundamental dimensions of how a practitioner relates to knowledge in any craft.

**Axis 1 — Action vs. Cognition (vertical)**

A skill contains both *practical* knowledge (knowing how — what we do) and *theoretical* knowledge (knowing that — what we think). These are wholly distinct from each other, though deeply intertwined.

- **Action** (top of the map): The user needs to *do* something. Documentation in this half guides hands-on activity.
- **Cognition** (bottom of the map): The user needs to *know* or *understand* something. Documentation in this half conveys information or builds mental models.

**Axis 2 — Acquisition vs. Application (horizontal)**

At any given moment, a practitioner is either acquiring new capability or applying existing capability.

- **Acquisition** (left of the map): The user is *studying* — learning new skills or gaining new understanding. They are in a mode of education and exploration.
- **Application** (right of the map): The user is *working* — using skills and knowledge they already possess to accomplish a real-world goal.

### The Quadrant Map

These two axes produce four quadrants. Each quadrant corresponds to one documentation type:

```
                  ACTION (practical / doing)
                       │
         TUTORIALS     │     HOW-TO GUIDES
       (learning-      │     (task-
        oriented)      │      oriented)
                       │
  ACQUISITION ─────────┼───────── APPLICATION
  (study)              │          (work)
                       │
       EXPLANATION     │     REFERENCE
       (understanding- │     (information-
        oriented)      │      oriented)
                       │
                  COGNITION (theoretical / knowing)
```

| Quadrant | Type | User mode | Documentation serves... |
|---|---|---|---|
| Top-left | Tutorial | Studying + Doing | Learning through guided practice |
| Top-right | How-To Guide | Working + Doing | Completing a specific real-world task |
| Bottom-left | Explanation | Studying + Thinking | Deepening understanding of a topic |
| Bottom-right | Reference | Working + Thinking | Looking up accurate technical facts |

This is not merely a classification list — it is a *conceptual arrangement*. The spatial relationships between the quadrants matter. Adjacent types share one axis and are therefore related (e.g. Tutorials and How-To Guides both involve action; Tutorials and Explanation both serve the studying user). Diagonal types share neither axis and are the most different from each other.

---

## The Four Documentation Types

### 1. Tutorials — Learning-Oriented

**What it is:** A tutorial is a *lesson*. It takes a learner by the hand through a carefully constructed learning experience. The learner does things, guided step by step by an instructor. A driving lesson is a useful analogy: the purpose is not to get from A to B, but to develop skills and confidence in the student.

**The user's need:** "I am a newcomer. Help me get started and give me a successful first experience."

**Key characteristics:**

- The tutorial author is responsible for the learner's success. If the student fails, the tutorial has failed — not the student.
- The tutorial must work *every time*. It must be tested and maintained against the current state of the software.
- It teaches through *doing*, not through explanation. The learner acquires understanding implicitly from concrete actions, not from being told abstract concepts.
- It has a meaningful, achievable end-state that the learner can see themselves building towards from the start.
- Each step produces a *visible, meaningful result* so the learner can connect cause and effect.

**Writing principles:**

- Provide the learner with everything they need. Do not assume prior knowledge.
- Ensure every step works as described. Invest in testing.
- Keep the learner focused. Do not offer choices, variations, or digressions.
- Provide only the *minimum* explanation necessary ("We use HTTPS because it's more secure"). Link to deeper explanation material for later.
- Focus on the concrete and particular. The human mind perceives general patterns from specific examples — abstraction is the *outcome* of learning, not the input.
- Maintain the learner's flow. Move from one concrete action and result to the next.

**Common mistakes:**

- Overloading tutorials with explanation (the most frequent error). This disrupts the learner's flow and replaces doing with reading.
- Offering choices or alternative approaches. The learner does not yet have the context to choose meaningfully.
- Teaching concepts instead of guiding actions.
- Assuming the tutorial is "the basics" — tutorials are about *learning*, which is a distinct mode regardless of difficulty level.

**Analogy:** A cooking class where the instructor walks a student through making a dish for the first time.

---

### 2. How-To Guides — Task-Oriented

**What it is:** A how-to guide is a set of practical directions that help an already-competent user accomplish a specific real-world task. It assumes the reader has baseline knowledge and is solving a concrete problem. A recipe is the canonical analogy: it tells you what to do to achieve a defined result, and even a professional chef may follow one.

**The user's need:** "I need to accomplish X. Show me how."

**Key characteristics:**

- It addresses a *specific goal or problem*. "How to configure reconnection back-off policies" is a how-to guide. "How to build a web application" is not — that's a vastly open-ended sphere, not a specific task.
- It assumes competence. The reader already knows how to use the product; they need directions for a particular task.
- It is concerned with *work*, not *study*.
- The list of how-to guides in a documentation set frames the picture of what the product can actually do. A rich collection of how-to guides signals capability and encourages adoption.

**Writing principles:**

- Title the guide with a clear, specific action: "How to [verb] [noun]". Good titles serve both human readers and search engines.
- Use a *sequence* of steps. The fundamental structure is ordered action. The ordering should reflect how the user thinks and works, not just how the machinery operates.
- Achieve *flow*: smooth progress that avoids unnecessary context-switching between tools, concepts, or environments.
- Be practical, not exhaustive. Link to the reference material for complete option lists rather than inlining every possibility.
- A how-to guide does not need to be end-to-end. It should start and end at reasonable, meaningful points and expect the reader to connect it to their own work.
- Use conditional imperatives: "If you want X, do Y."

**Common mistakes:**

- Conflating how-to guides with tutorials. This is the single most common documentation error. Tutorials serve learners; how-to guides serve practitioners.
- Trying to teach within a how-to guide. The reader already knows how to use the product — just show them the path.
- Making guides too comprehensive. Practical usability matters more than completeness.
- Titling guides vaguely (e.g. "Authentication" instead of "How to configure OAuth2 authentication").

**Analogy:** A recipe in a cookbook. It assumes cooking skill and tells you what to do for a specific dish.

---

### 3. Reference — Information-Oriented

**What it is:** Reference documentation is technical description: facts that a user needs to do things correctly. It is accurate, complete, and neutral. It describes the machinery — APIs, classes, functions, configuration options, CLI commands, error messages — and how to operate it. A map or a dictionary are good analogies.

**The user's need:** "I need to look up the exact specification / parameters / behaviour of X."

**Key characteristics:**

- It is consulted, not read cover-to-cover. Users come to it while working, to check a specific fact.
- Its structure should mirror the structure of the thing it describes, just as a map mirrors the territory. If a method belongs to a class in a module, the documentation should reflect that same hierarchy.
- It is *austere and neutral*. Reference material serves any user regardless of what they are trying to accomplish.
- It must be *accurate* and *complete*. Users rely on reference material as a source of truth.

**Writing principles:**

- *Describe*, do not explain or instruct. State facts about the machinery and its behaviour.
- Maintain a consistent structure and tone across all reference entries.
- Mirror the architecture of the code or product. This makes gaps clearly visible.
- Use examples sparingly and only to *illustrate* (e.g. a usage example for a CLI command), not to teach or explain.
- List commands, options, operations, features, flags, limitations, error messages, etc.
- Where possible, auto-generate reference documentation from the code itself (e.g. from docstrings, OpenAPI specs, type definitions).
- Keep reference material rigorously separated from tutorials and how-to guides. This makes everything else clearer too.

**Common mistakes:**

- Injecting explanation or instruction into reference material. Resist the urge — link to those other documentation types instead.
- Inconsistent formatting. Reference docs should feel uniform and predictable.
- Incomplete coverage. Unlike other documentation types, reference has a clear completeness criterion: every public interface should be documented.
- Structuring reference by an arbitrary order rather than mirroring the product's own architecture.

**Analogy:** The nutritional information panel on food packaging, or a nautical chart. Factual, structured, neutral, reliable.

---

### 4. Explanation — Understanding-Oriented

**What it is:** Explanation is *discussion* that illuminates a topic. It provides background, context, rationale, history, design decisions, alternatives, and connections. It answers "why?" questions and helps the user move from working familiarity to genuine understanding. A knowledgeable friend sharing insight is the closest analogy.

**The user's need:** "I want to understand *why* things are the way they are. Help me see the bigger picture."

**Key characteristics:**

- It takes a higher, wider perspective than the other three types. It is not at the user's eye-level (like a how-to guide) or at close-up detail (like reference). Its scope is a *topic* — a bounded area of knowledge.
- It can and should contain opinions, perspectives, alternatives, counter-examples, and historical context. Understanding comes from seeing a subject from multiple angles.
- It is the only documentation type that makes sense to read away from the product (as Procida puts it, "the only kind of documentation you might read in the bath").
- It joins things together. It builds the web of understanding that holds a practitioner's other knowledge together and makes it durable.

**Writing principles:**

- Structure around topics, not tasks or components. Guide titles should work with an implicit "About…" prefix: "About user authentication", "About database connection policies".
- Circle around the subject. Approach it from different directions. Make connections to related topics.
- Offer context, judgement, and opinion where appropriate. "Some users prefer X because Y. This can be a good approach, but…"
- Provide analogies, comparisons, and historical background.
- Do *not* instruct or describe facts inline — link to how-to guides and reference material for those purposes.
- Weigh up alternatives and trade-offs.

**Common mistakes:**

- Explanation absorbing other types of content. The writer, intent on covering a topic thoroughly, starts including step-by-step instructions or API descriptions. These belong elsewhere.
- Being dismissed as unimportant because it has no direct practical application. Explanation is what transforms fragile, fragmented knowledge into durable understanding.
- Writing explanation that is really just verbose reference or an extended tutorial preamble.

**Analogy:** Harold McGee's *On Food and Cooking* — it doesn't contain recipes and it isn't reference material. It illuminates the science and history of cooking so that you *understand* what you're doing and why.

---

## The Compass: A Decision Tool

The Diátaxis compass is a practical tool for making documentation decisions. When you are unsure where a piece of content belongs, or when something you're writing feels wrong, ask two questions:

1. **Is this about action, or cognition?**
   - Action → the user needs to *do* something (top half of the map)
   - Cognition → the user needs to *know* or *understand* something (bottom half)

2. **Is this about acquisition, or application?**
   - Acquisition → the user is *studying/learning* (left half of the map)
   - Application → the user is *working/applying* (right half of the map)

The intersection of the two answers identifies the correct documentation type:

| | Acquisition (study) | Application (work) |
|---|---|---|
| **Action** (doing) | Tutorial | How-To Guide |
| **Cognition** (knowing) | Explanation | Reference |

The compass is most powerful precisely when you feel doubt — when you think you're writing one type of content but something feels off. It forces you to reconsider by reducing the two-dimensional classification problem to two simple binary questions.

---

## Relationships and Boundaries

### Adjacent types share qualities

- **Tutorials ↔ How-To Guides:** Both are practical, action-oriented, step-based. The critical difference is that tutorials serve a *learner* while how-to guides serve a *worker*. Conflating them is the single most common documentation error.
- **Tutorials ↔ Explanation:** Both serve the *studying* user who is acquiring new capability. But tutorials work through action while explanation works through understanding.
- **How-To Guides ↔ Reference:** Both serve the *working* user. But how-to guides direct action while reference provides facts.
- **Explanation ↔ Reference:** Both deal in knowledge and cognition. But reference is neutral factual description, while explanation offers interpretation, perspective, and context.

### Crossing boundaries causes problems

Most documentation problems arise from mixing types. Typical examples:

- A **tutorial that explains too much** — the learner's flow of action is disrupted by theoretical digressions.
- A **how-to guide that tries to teach** — the competent user is slowed down by explanations they don't need.
- **Reference material that includes instructions** — factual descriptions become muddied with "how to use this" content.
- **Explanation that drifts into reference** — a discussion about why something works the way it does turns into a dry description of every parameter.

When you notice content from one type intruding into another, the correct response is to *extract* it and *link* to it in the appropriate section, not to delete it. The content is valuable — it's just in the wrong place.

---

## Workflow: Applying Diátaxis in Practice

Diátaxis advocates an iterative, bottom-up approach rather than top-down planning.

### Start from where you are

1. Look at your documentation as it exists right now (which may be nothing).
2. Ask: is there *one thing* I could do to improve it, however small, according to Diátaxis principles?
3. Do that thing.
4. Repeat.

### Do not create empty structures

Do not start by creating four empty sections titled "Tutorials / How-To Guides / Reference / Explanation" with nothing in them. This is counterproductive. Instead, make small improvements within existing content. Move a paragraph of explanation out of a tutorial. Extract inline API descriptions into a reference page. Over time, the structure will emerge organically from the inside.

### Expect things to look worse first

Applying Diátaxis initially makes existing problems more visible, not less. Content in the wrong place stands out starkly. This is a feature, not a bug — problems cannot be solved until they are clearly seen.

### Use the compass, not a plan

When working on a specific piece of content, use the compass questions (action/cognition? acquisition/application?) to orient yourself. This is more productive than trying to classify an entire documentation set at once.

### Documentation is never finished

Like a living organism, documentation is always growing and adapting. At every stage, it can be complete for its current level of maturity while still having room to develop further. Diátaxis helps ensure that the documentation is well-formed at every stage, not just at some imagined "finished" state.

---

## Quick-Reference Cheat Sheet

### Per-Type Summary Table

| Attribute | Tutorial | How-To Guide | Reference | Explanation |
|---|---|---|---|---|
| **Orientation** | Learning | Task/Goal | Information | Understanding |
| **User mode** | Studying | Working | Working | Studying |
| **Knowledge type** | Practical (action) | Practical (action) | Theoretical (cognition) | Theoretical (cognition) |
| **Analogy** | A driving lesson | A recipe | A map / dictionary | A magazine article |
| **Answers the question** | "Can you teach me to…?" | "How do I…?" | "What is the X for Y?" | "Can you explain why…?" |
| **Author responsibility** | Learner's success | Clear directions | Accuracy & completeness | Illumination & context |
| **Must not include** | Explanation, choices | Teaching, completeness | Instruction, opinion | Steps, technical specs |
| **Structure follows** | The learning journey | The task's logical steps | The product's architecture | The topic's conceptual shape |
| **Tone** | Encouraging, guiding | Direct, practical | Neutral, austere | Discursive, reflective |

### Common Diagnostic Questions

When evaluating documentation, ask:

- "Am I explaining *why* inside a tutorial?" → Extract to Explanation, link.
- "Am I teaching fundamentals inside a how-to guide?" → That's tutorial content.
- "Am I showing *how to use* something inside reference?" → Extract to a How-To Guide.
- "Am I listing API parameters inside an explanation?" → That belongs in Reference.
- "Does this how-to guide assume no prior knowledge?" → It might actually be a tutorial.
- "Is this tutorial addressing a specific real-world problem?" → It might be a how-to guide.

---

## For AI-Assisted Documentation

Declare the documentation type before writing. This single decision constrains tone, structure, and content boundaries. Replace `{{placeholders}}` with your specifics.

### Generation Prompts

#### Tutorial

```
Write a TUTORIAL for {{product}}. Topic: {{what the learner builds}}.

Audience: Newcomer, no prior {{product}} experience.
Tone: Encouraging, use "we". Patient instructor voice.

Rules:
- Open with: "In this tutorial we will [outcome]. Along the way we will encounter [concepts]."
- Every step: action → visible result → confirmation of what the learner should see.
- Provide everything needed: exact commands, file contents, expected outputs.
- NEVER explain why things work — one clause max ("We use X because it's safer"), then link out.
- NEVER offer choices or alternatives. One path only.
- NEVER digress into features, options, or abstract descriptions.

Structure: intro → prerequisites (exact) → numbered steps → conclusion with links to how-to/explanation.
```

#### How-To Guide

```
Write a HOW-TO GUIDE for {{product}}. Task: {{specific goal}}.

Audience: Competent practitioner solving a real problem right now.
Tone: Direct, practical, confident. Colleague giving directions.

Rules:
- Title: "How to {{verb}} {{noun}}".
- Open: 1–2 sentences on what this achieves and when you'd need it.
- Steps are actions or decisions. Use conditional imperatives: "If you need X, set Y to Z."
- NEVER teach fundamentals — the reader already knows them. Link to tutorials if needed.
- NEVER list exhaustive options — link to reference instead.
- NEVER explain background/rationale — link to explanation instead.

Structure: title → context → prerequisites (minimal) → numbered steps → optional troubleshooting.
```

#### Reference

```
Write REFERENCE documentation for {{product}}. Subject: {{component/API/CLI}}.

Audience: Practitioner looking up a specific fact while working. They scan, not read.
Tone: Neutral, austere, precise. Present tense, active voice. No first/second person.

Rules:
- DESCRIBE the machinery: what it is, accepts, returns, does, edge cases, defaults, errors.
- Mirror the product's own architecture in the documentation structure.
- Be accurate, complete, and consistent across all entries of the same kind.
- NEVER explain why something is designed a certain way — link to explanation.
- NEVER give step-by-step task instructions — link to how-to guides.
- NEVER teach or recommend. Reference is neutral.
- One minimal usage example per entry to illustrate, not to teach.

Entry structure: name/signature → description → parameters → returns → errors → example → notes.
```

#### Explanation

```
Write an EXPLANATION article for {{product}}. Topic: {{subject to illuminate}}.

Audience: User with working familiarity who wants deeper understanding. May be reading away from the product.
Tone: Discursive, reflective, collegial. Knowledgeable friend sharing insight.

Rules:
- Title works with implicit "About…" prefix: "About {{topic}}" or "Why {{product}} uses {{approach}}".
- Take a higher, wider perspective. Connect to design philosophy, history, and broader concepts.
- Approach from multiple angles: context, rationale, trade-offs, alternatives, analogies.
- Offer judgement and opinion: "Some prefer X because Y. This works well when Z, but…"
- NEVER embed step-by-step instructions — link to how-to guides.
- NEVER catalogue full API surfaces or parameter lists — link to reference.
- NEVER structure as a guided exercise — this is not a tutorial.

Structure: framing paragraph → conceptual sub-topics in prose → closing insight with cross-links.
```

### Review Prompts

#### Boundary Violation Review

```
Review this document (declared type: {{type}}) for Diátaxis boundary violations.

For each section, classify using the compass:
- Action or Cognition? → Acquisition or Application?
- Action+Acquisition=Tutorial, Action+Application=How-To, Cognition+Acquisition=Explanation, Cognition+Application=Reference.

Output: table of [Section], [Declared Type], [Actual Type], [Issue], [Fix].
Watch for: explanation in tutorials (#1 error), teaching in how-tos, instructions in reference, specs in explanation.
```

#### Content Classification

```
Classify each section of this unstructured document using the Diátaxis compass:
1. Action or Cognition?  2. Acquisition or Application?

Action+Acquisition=Tutorial, Action+Application=How-To, Cognition+Acquisition=Explanation, Cognition+Application=Reference.

Per block output: summary, compass answers, type, confidence (high/medium/low), split points if mixed.
End with a migration plan: new pages to create, content moves, cross-links needed.
```

---

## Further Reading

- **Official site:** [diataxis.fr](https://diataxis.fr/)
- **GitHub repository:** [evildmp/diataxis-documentation-framework](https://github.com/evildmp/diataxis-documentation-framework)
- **Original talk:** Daniele Procida, "What nobody tells you about documentation" (PyCon)
- **Community:** `#diataxis` channel on the [Write the Docs Slack](https://www.writethedocs.org/slack/)
