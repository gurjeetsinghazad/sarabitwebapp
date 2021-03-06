<?php

namespace App\Controller;

use App\Entity\PageSection;
use App\Entity\PageSectionImages;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/myadmin")
 */
class PageSectionImageController extends BaseController {

    /**
     * @Route("/page/section/{id}/image", name="myadmin_page_section_images")
     */
    public function index(Request $request, DataTableFactory $dtf) {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $section = $em->getRepository(PageSection::class)->findOneBy(['id' => $id]);
        $datatable = $dtf->create()
                        ->add('rank', NumberColumn::class, ['label' => 'Rank'])
                        ->add('title', TextColumn::class, ['label' => 'Title'])
                        ->add('image', TextColumn::class, [
                            'label' => 'Image',
                            'render' => function($c, $v) {
                                $src = $this->generateUrl('page_section_images', ['id' => $v->getId(), 'name' => $v->getId()]);
                                $html = "<img src='" . $src . "' height='120px'>";
                                return $html;
                            }
                        ])
                        ->add('action', TextColumn::class, ['label' => 'Edit', 'render' => function($c, $v) {
                                $editRoute = $this->generateUrl('myadmin_page_section_image_edit_content', ['sectionId' => $v->getPageSection()->getId(), 'imageId' => $v->getId()]);
                                $deleteRoute = $this->generateUrl('myadmin_page_section_image_delete', ['sectionId' => $v->getPageSection()->getId(), 'imageId' => $v->getId()]);
                                $html = '<div class="btn-group">';
                                $html .= "<a class='btn btn-sm btn-primary aic-show-large-modal' href='javascript:void(0)' data-href='" . $editRoute . "'>Edit</a>";
                                $html .= '<a class="btn btn-sm btn-danger" href="' . $deleteRoute . '">Delete</a>';
                                $html .= '</div>';
                                return $html;
                            }])
                        ->addOrderBy('rank', DataTable::SORT_ASCENDING)
                        ->createAdapter(ORMAdapter::class, [
                            'entity' => PageSection::class,
                            'query' => function (QueryBuilder $builder) use ($section) {
                                $builder
                                ->select('p')
                                ->from(PageSectionImages::class, 'p')
                                ->where('p.pageSection = :section')
                                ->setParameter('section', $section->getId())
                                ;
                            },
                        ])->handleRequest($request);

        if ($datatable->isCallback()) {
            return $datatable->getResponse();
        }
        return $this->render('page_section_image/index.html.twig', [
                    'datatable' => $datatable,
                    'section' => $section,
        ]);
    }

    /**
     * @Route("/page/section/{sectionId}/image/{imageId}/delete/image", name="myadmin_page_section_image_delete")
     */
    public function deleteImage(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $sectionId = $request->get('sectionId');
        $imageId = $request->get('imageId');
        $pageSectionImages = $em->getRepository(PageSectionImages::class)->findOneBy(['pageSection' => $sectionId, 'id' => $imageId]);
        $em->remove($pageSectionImages);
        $em->flush();
        return $this->redirectToReffer($request);
    }

    /**
     * @Route("/page/section/{sectionId}/image/{imageId}/edit/content", name="myadmin_page_section_image_edit_content")
     */
    public function editContent(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $sectionId = $request->get('sectionId');
        $imageId = $request->get('imageId');
        $image = $em->getRepository(PageSectionImages::class)->findOneBy(['id' => $imageId, 'pageSection' => $sectionId]);
        $form = $this->createFormBuilder($image)
                ->setAction($this->generateUrl('myadmin_page_section_image_edit_content', ['sectionId' => $sectionId, 'imageId' => $imageId]))
                ->add('title')
                ->add('description')
                ->add('showTitle', ChoiceType::class, ['choices' => ['yes' => 'yes', 'no' => 'no']])
                ->add('showDescription', ChoiceType::class, ['choices' => ['yes' => 'yes', 'no' => 'no']])
                ->add('titleColor', ColorType::class)
                ->add('descriptionColor', ColorType::class)
                ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($image);
            $em->flush();

            return $this->redirectToRoute('myadmin_page_section_images', ['id' => $sectionId]);
        }
        return $this->render('page_section_image/modalForm.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/page/section/{sectionId}/image/new", name="myadmin_page_section_image_new")
     */
    public function newImage(Request $request) {
        $sectionId = $request->get('sectionId');
        $em = $this->getDoctrine()->getManager();
        $section = $em->getRepository(PageSection::class)->findOneBy(['id' => $sectionId]);
        $image = new PageSectionImages();
        $form = $this->createFormBuilder($image)
                ->setAction($this->generateUrl('myadmin_page_section_image_new', ['sectionId' => $sectionId]))
                ->add('title')
                ->add("fileData", FileType::class)
                ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $_FILES['form'];
            $fileName = $file['name']['fileData'];
            $fileType = $file['type']['fileData'];
            $fileTmpName = $file['tmp_name']['fileData'];
            $image->setFileData(file_get_contents($fileTmpName))
                    ->setFileName($fileName)
                    ->setFileType($fileType)
                    ->setPageSection($section);
            $lastImage = $em->getRepository(PageSectionImages::class)->findOneBy(['pageSection' => $section->getId()], ['rank' => 'desc']);
            if ($lastImage) {
                $rank = $lastImage->getRank() + 1;
            } else {
                $rank = 1;
            }
            $image->setRank($rank);
            $em->persist($image);
            $em->flush();
            return $this->redirectToRoute('myadmin_page_section_images', ['id' => $section->getId()]);
        }
        return $this->render('page_section_image/modalForm.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/myadmin/page/section/reorder/images", name="myadmin_page_section_reorder_image", methods={"GET","POST"})
     */
    public function pageSectionImageReorder(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $data = $request->get('data');
        foreach ($data as $d) {
            $row = $em->getRepository(PageSectionImages::class)->findOneBy(['pageSection' => $request->get('id'), 'rank' => $d['old']]);
            $row->setRank($d['new']);
            $em->persist($row);
        }
        $em->flush();
        return new JsonResponse(['true']);
    }

}
