<?php

namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogController extends AbstractController
{
    //_________________________________BUSCAR POSTS_______________________________________//
    #[Route("/blog/buscar/{page}", name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine,  Request $request, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get("searchTerm"??"");
        if(!empty($searchTerm))
            $posts= $repository->findByText($page, $request->query->get("searchTerm"??""));

        dump($posts);
        exit;
    } 


    //_________________________________CREAR NUEVO POST_______________________________________//
    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post(); //NUEVO POST_↓↓
        $form = $this->createForm(PostFormType::class, $post);//EL FORMULARIO CORRESPONDE A UNA CLASE YA CREADA EN:App\Form\PostFormType
        $form->handleRequest($request);
                            //___________↑↑

        //CUANDO MANDAS EL POST↓↓
        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();   
            $post->setSlug($slugger->slug($post->getTitle())); //TOMA LA VARIABLE TITTLE DEL FORM Y PONE "SLUG" CON ESTE VALOR.
            $post->setUser($this->getUser());              //LO MISMO CON LA VARIABLE USER Y POSTUSER
            $file = $form->get('Image')->getData();
            //PARTE DE LA SUBIDA DE LA IMAGEN___________________________________________
                if ($file) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    // this is needed to safely include the file name as part of the URL
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
            
                    // Move the file to the directory where images are stored
                    try {
            
                        $file->move(
                            $this->getParameter('images_directory'), $newFilename
                        );
                        $filesystem = new Filesystem();
                        $filesystem->copy(
                            $this->getParameter('images_directory') . '/'. $newFilename, 
                            $this->getParameter('portfolio_directory') . '/'.  $newFilename, true);
            
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                    }
                    $post->setImage($newFilename);
                }
                //_____________________________________________
            $post->setNumLikes(0);
            $post->setNumComments(0);
            $post->setNumViews(0);
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($post); //PERSISTE
            $entityManager->flush();        //Y GUARDA TODO

            return $this->redirectToRoute('single_post', ['Slug' => $post->getSlug()]);
        }

        //
        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView()    
        ));
    }





//_________________________________MOSTRAR UN SOLO BLOG_______________________________________//
#[Route('/single_post/{Slug}', name: 'single_post')]
public function post(ManagerRegistry $doctrine, $Slug): Response
{
    $repositorio = $doctrine->getRepository(Post::class);
    $post = $repositorio->findOneBy(["Slug"=>$Slug]);
    return $this->render('blog/single_post.html.twig', [
        'post' => $post,
    ]);
}




    
    //_________________________________MOSTRAR TODOS LOS BLOG_______________________________________//
    #[Route("/blog", name: 'blog')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();
        $recents = $repository->findRecents();
        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
        ]);
    }


    //_________________________________PONER LIKE A UN POST_______________________________________/


    #[Route("/blog/{slug}/like", name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $Slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["Slug" => $Slug]);
        $post->addLike();
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($post); //PERSISTE
        $entityManager->flush();        //Y GUARDA TODO
        return $this->redirectToRoute('single_post', ['Slug'=>$Slug]);

    }



}