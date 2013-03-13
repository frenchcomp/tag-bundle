## Installation 

### Use composer. Include in your composer.json 

```json
"cmedia/tag-bundle": "dev-master" 
```
### Register bundle in app/AppKernel.php

```php
// ...
new CMedia\Bundle\TagBundle\CMediaTagBundle(), 
// ...
```

## Usage example

Implement Taggable and TagContainable interfaces. 

```php
// Post.php
namespace Acme\DemoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use CMedia\Bundle\TagBundle\Entity\Interfaces\Taggable;
use CMedia\Bundle\TagBundle\Assistant\TagAssistant;

/**
 * Post
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Acme\DemoBundle\Entity\PostRepository")
 */
class Post implements Taggable
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * @var string $tags
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="posts", cascade={"persist"})
     * 
     */
    protected $tags;

    protected $tagString;

    public function getTagString()
    {
        return TagAssistant::tagArrayToString($this);
    }

    public function setTagString($tagString)
    {
        $this->tagString = $tagString;
    }

    public function getTagsInserted()
    {
        return $this->tagString;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function setTags(ArrayCollection $tags)
    {
        foreach ($tags as $tag) {
            $tag->addPost($this);
        }
        
        $this->tags = $tags;

        return $this;
    }

    public function __construct()
    {
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Post
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add tags
     *
     * @param \Acme\DemoBundle\Entity\Tag $tags
     * @return Post
     */
    public function addTag(\Acme\DemoBundle\Entity\Tag $tags)
    {
        $this->tags[] = $tags;
    
        return $this;
    }

    /**
     * Remove tags
     *
     * @param \Acme\DemoBundle\Entity\Tag $tags
     */
    public function removeTag(\Acme\DemoBundle\Entity\Tag $tags)
    {
        $this->tags->removeElement($tags);
    }
}
```

```php
// Tag.php

namespace Acme\DemoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use CMedia\Bundle\TagBundle\Entity\Interfaces\TagContainable;

/**
 * Tag
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Tag implements TagContainable
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /** @ORM\ManyToMany(targetEntity="Post", mappedBy="tags") */
    protected $posts;

    public function __construct()
    {
        $this->posts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Tag
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Add posts
     *
     * @param \Acme\DemoBundle\Entity\Post $posts
     * @return Tag
     */
    public function addPost(\Acme\DemoBundle\Entity\Post $posts)
    {
        $this->posts[] = $posts;
    
        return $this;
    }

    /**
     * Remove posts
     *
     * @param \Acme\DemoBundle\Entity\Post $posts
     */
    public function removePost(\Acme\DemoBundle\Entity\Post $posts)
    {
        $this->posts->removeElement($posts);
    }

    /**
     * Get posts
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getPosts()
    {
        return $this->posts;
    }
}
```

### In PostType.php don't include TagType itself. Use implemented ```$tagString``` instead

```php
// ...
->add('tagString', 'hidden')
// ...
```

### In your controller

```php
/**
 * Creates a new Post entity.
 *
 * @Route("/", name="post_create")
 * @Method("POST")
 * @Template("AcmeDemoBundle:Post:new.html.twig")
 */
public function createAction(Request $request)
{
    $entity  = new Post();
    $form = $this->createForm(new PostType(), $entity);
    $form->bind($request);

    if ($form->isValid()) {
        $em = $this->getDoctrine()->getManager();

        $this->get('cm_tag_assistant')->processTags($entity, "\Acme\DemoBundle\Entity\Tag");

        $em->persist($form->getData());
        $em->flush();

        return $this->redirect($this->generateUrl('post_show', array('id' => $entity->getId())));
    }

    return array(
        'entity' => $entity,
        'form'   => $form->createView(),
    );
}
```

### CMediaTagBundle comes with builting js TagManager library 
The library depends on jquery 8.2 and bootstrap plugin lib. 
*Note:* You can use your preferred library instead. Also you can use text filed for ```$tagString``` in PostType.php

### TagManager usage example:

```twig
<!-- in form -->
<input id="tag-manager" type="text" name="tags" autocomplete="off" data-provide="typeahead" placeholder="Tags" class="tagManager input-small"/>

<link rel="stylesheet" type="text/css" href="{{ asset('bundles/cmediatag/css/bootstrap-tagmanager.css') }}">

<script type="text/javascript" src="{{ asset('bundles/cmediatag/js/bootstrap-tagmanager.js') }}"></script>

{{ include('CMediaTagBundle::script.html.twig', {'tagManagerId': 'tagManager', 'tagListId': 'acme_demobundle_posttype_tagString', 'tagListName': 'acme_demobundle_posttype[tagString]', 'ajaxPath': ''}) }}
```