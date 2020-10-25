<?php

namespace App\Controller;

use App\Entity\GrapeBlock;
use App\Entity\Page;
use App\Entity\PageSection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Page controller.
 *
 */
class PageController extends BaseController {

    /**
     * Lists all page entities.
     *
     */
    public function indexAction() {
        $em = $this->getDoctrine()->getManager();

        $pages = $em->getRepository('App:Page')->findAll();


        return $this->render('page/index.html.twig', array(
                    'pages' => $pages,
        ));
    }

    /**
     * @Route("/myadmin/page.new.html", name="myadmin_page_new", methods={"GET","POST"})
     */
    public function newAction(Request $request) {
        $page = new Page();
        $form = $this->createForm('App\Form\PageType', $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $menu = $form['menu']->getData();
            $page->setTitle($form['title']->getData());
            $page->setName($form['name']->getData());
            $page->setUrl(trim($form['url']->getData()));
            $page->setTarget($form['target']->getData());
            $page->setKeywords(strtolower($form['keywords']->getData()));
            $page->setDescription($form['description']->getData());
            $page->setMenu($menu);
            $page->setIsHome(0);
            $page->setRank(0);
            $page->setBody($form['body']->getData())
                    ->setCss(' ');
            $em->persist($page);
            $em->flush();

            return $this->redirectToRoute('myadmin_page_edit', array('id' => $page->getId()));
        }

        return $this->render('admin/page/new.html.twig', array(
                    'page' => $page,
                    'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/myadmin/page/{id}/delete", name="myadmin_page_delete", methods="GET")
     */
    public function deleteAction(Request $request) {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository('App:Page')->findOneBy(['id' => $id]);
        if (count($page) == 1) {
            $em->remove($page);
            //$em->persist($page);
            $em->flush();
        }
        return $this->redirectToRoute('myadmin_page_index');
    }

    /**
     * Finds and displays a page entity.
     *
     */

    /**
     * @Route("/p.{id}.{slug}.html", name="page_show", methods="GET")
     */
    public function showAction(Request $request) {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $id]);
        $body = $this->bodyFilter($page->getBody());
        $response = $this->render('business/page.html.twig', array(
            'page' => $page,
            'body' => $body,
        ));

        return $this->etagResponse($response, $request);
    }

    /**
     * Displays a form to edit an existing page entity.
     *
     */

    /**
     * @Route("/myadmin/open/page_{id}.html", name="myadmin_page_open", methods={"GET","POST"})
     */
    public function openAction(Request $request, DataTableFactory $dtf) {
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $request->get('id')]);
        $datatable = $dtf->create()
                        ->add('rank', NumberColumn::class, ['label' => 'Rank'])
                        ->add('title', TextColumn::class, ['label' => 'Title'])
                        ->createAdapter(ORMAdapter::class, [
                            'entity' => PageSection::class,
                            'query' => function (QueryBuilder $builder) {
                                $builder
                                ->select('p')
                                ->from(PageSection::class, 'p')
                                ;
                            },
                        ])->handleRequest($request);

        if ($datatable->isCallback()) {
            return $datatable->getResponse();
        }
        return $this->render('admin/page/open.html.twig', [
                    'page' => $page,
                    'datatable' => $datatable
        ]);
    }

    /**
     * @Route("/myadmin/new/section_{id}.html", name="myadmin_page_new_section", methods={"GET","POST"})
     */
    public function newSection(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $id = $request->get('id');
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $id]);
        $lastPage = $em->getRepository(PageSection::class)->findOneBy(['page' => $page->getId()], ['rank' => 'desc']);
        $form = $this->createFormBuilder()
                ->setAction($this->generateUrl('myadmin_page_new_section', ['id' => $id]))
                ->add('title', TextType::class)
                ->add('type', ChoiceType::class, ['choices' => [
                        'simple' => 'simple'
            ]])
                ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $section = new PageSection();
                $section->setTitle($form['title']->getData());
                if ($form['type']->getData() == 'simple') {
                    $section->setContent($this->getLongLerom());
                }
                if ($lastPage) {
                    $rank = $lastPage->getRank() + 1;
                } else {
                    $rank = 1;
                }
                $section->setRank($rank)
                        ->setPage($page)
                        ->setType($form['type']->getData());
                $em->persist($section);
                $em->flush();
                return $this->redirectToRoute('myadmin_page_open', ['id' => $page->getId()]);
            }
        }
        return $this->render('admin/page/newSection.html.twig', [
                    'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/myadmin/page.edit.html", name="myadmin_page_edit", methods={"GET","POST"})
     */
    public function editAction(Request $request) {
        //$deleteForm = $this->createDeleteForm($page);
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $request->get('id')]);
        // $editForm = $this->createForm('App\Form\PageType', $page);
        $formBuilder = $this->createFormBuilder($page);
        if ($page->getIsHome() == TRUE) {
            $formBuilder->add('name', TextType::class, ['attr' => ['readonly' => 'readonly']])
                    ->add('menu', EntityType::class, array(
                        'class' => 'App:Menu',
                        'query_builder' => function (EntityRepository $er) {
                            return $er->createQueryBuilder('M')
                                    ->where('M.name = :name')
                                    ->setParameter('name', 'Home')
                                    ->orderBy('M.name', 'ASC');
                        },
                        'choice_label' => 'name',
            ));
        } else {
            $formBuilder->add('name')
                    ->add('menu', EntityType::class, array(
                        'class' => 'App:Menu',
                        'query_builder' => function (EntityRepository $er) {
                            return $er->createQueryBuilder('M')
                                    ->where('M.name != :name')
                                    ->setParameter('name', 'Home')
                                    ->orderBy('M.name', 'ASC');
                        },
                        'choice_label' => 'name',
            ));
        }

        $formBuilder->add('title')
                ->add('keywords', TextType::class)
                ->add('description', TextType::class)
                ->add('body', TextareaType::class, array('attr' => array('novalidate' => 'novalidate')))
                ->add('url', TextType::class, array('required' => false))
                ->add('target', ChoiceType::class, array('choices' => array('Open in Same Window' => '_top', 'Open In New Window' => '_blank')))
                ->add('rank');
        $editForm = $formBuilder->getForm();
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            //$this->getDoctrine()->getManager()->flush();
            $em = $this->getDoctrine()->getManager();
            if ($page->getIsHome() == TRUE) {
                $page->setName('Home');
            }
            $em->persist($page);
            $em->flush();
            $this->addFlash(
                    'success', '<b>Success!</b><br> Content Saved...'
            );
            return $this->redirectToRoute('myadmin_page_edit', array('id' => $page->getId()));
        }

        return $this->render('admin/page/edit.html.twig', array(
                    'page' => $page,
                    'edit_form' => $editForm->createView(),
//
        ));
    }

    /**
     * @Route("/myadmin/pagebuilder/save", name="myadmin_page_edit_grape_save", methods={"GET","POST"})
     */
    public function grapesSaveAction(Request $request) {
        $id = $request->get('id');
        $html = $request->get('html');
        $css = $request->get('css');
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $id]);
        $page->setBody($html)
                ->setCss($css);
        $em->persist($page);
        $em->flush();
        echo 'done';
        die;
    }

    /**
     * @Route("/myadmin/pagebuilder/page.edit.html", name="myadmin_page_edit_grape", methods={"GET","POST"})
     */
    public function grapesAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $request->get('id')]);
        $blocks = $em->getRepository(GrapeBlock::class)->findAll();
        $imageAssets = file_get_contents($this->generateUrl('image_assets_json', [], UrlGeneratorInterface::ABSOLUTE_URL));
        return $this->render('admin/page/grapes.html.twig', ['blocks' => $blocks, 'page' => $page, 'imageAssets' => $imageAssets]);
    }

    /**
     * @Route("/myadmin/page.edit2.html", name="myadmin_page_edit2", methods={"GET","POST"})
     */
    public function edit2Action(Request $request) {

        //$deleteForm = $this->createDeleteForm($page);
        $em = $this->getDoctrine()->getManager();
        $page = $em->getRepository(Page::class)->findOneBy(['id' => $request->get('id')]);
        // $editForm = $this->createForm('App\Form\PageType', $page);
        $formBuilder = $this->createFormBuilder($page);
        if ($page->getIsHome() == TRUE) {
            $formBuilder->add('name', TextType::class, ['attr' => ['readonly' => 'readonly']])
                    ->add('menu', EntityType::class, array(
                        'class' => 'App:Menu',
                        'query_builder' => function (EntityRepository $er) {
                            return $er->createQueryBuilder('M')
                                    ->where('M.name = :name')
                                    ->setParameter('name', 'Home')
                                    ->orderBy('M.name', 'ASC');
                        },
                        'choice_label' => 'name',
            ));
        } else {
            $formBuilder->add('name')
                    ->add('menu', EntityType::class, array(
                        'class' => 'App:Menu',
                        'query_builder' => function (EntityRepository $er) {
                            return $er->createQueryBuilder('M')
                                    ->where('M.name != :name')
                                    ->setParameter('name', 'Home')
                                    ->orderBy('M.name', 'ASC');
                        },
                        'choice_label' => 'name',
            ));
        }

        $formBuilder
                ->add('body', TextareaType::class, array('attr' => array('novalidate' => 'novalidate')))
        ;
        $editForm = $formBuilder->getForm();
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            //$this->getDoctrine()->getManager()->flush();
            $em = $this->getDoctrine()->getManager();
            if ($page->getIsHome() == TRUE) {
                $page->setName('Home');
            }
            $em->persist($page);
            $em->flush();
            $this->addFlash(
                    'success', '<b>Success!</b><br> Content Saved...'
            );
            return $this->redirectToRoute('myadmin_page_edit2', array('id' => $page->getId()));
        }

        return $this->render('admin/page/edit2.html.twig', array(
                    'page' => $page,
                    'edit_form' => $editForm->createView(),
//
        ));
    }

    /**
     * Creates a form to delete a page entity.
     *
     * @param Page $page The page entity
     *
     * @return Form The form
     */
    private function createDeleteForm(Page $page) {
        return $this->createFormBuilder()
                        ->setAction($this->generateUrl('page_delete', array('id' => $page->getId())))
                        ->setMethod('DELETE')
                        ->getForm()
        ;
    }

}
