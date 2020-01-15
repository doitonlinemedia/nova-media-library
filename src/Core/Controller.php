<?php

namespace ClassicO\NovaMediaLibrary\Core;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Controller {

	private $model = null;

	public function __construct() {
		$this->model = new Model;

	}

	/**
	 * Get all media files.
	 * You can filter by `description`, `type` or `created` date
	 *
	 * @return array
	 */
	public function get()
	{
		$valid_from = Validator::make(request()->only('from'), ['from' => 'nullable|date_format:Y-m-d']);
		$valid_to = Validator::make(request()->only('to'), ['to' => 'nullable|date_format:Y-m-d']);

		$from = $valid_from->fails() ? null : request('from');
		$to   = $valid_to->fails()   ? null : request('to');

		return $this->model->search(
			trim(htmlspecialchars(request('description', ''))),
			request('type'),
			request('step'),
			$from,
			$to
		);
	}

	/** Upload image to storage */
	function upload()
	{
        if(request()->has('file')) {
            $file = request()->file('file');
            $file_error = " ({$file->getClientOriginalName()})";
            if (!$file) abort(422, __('nova-media-library::messages.not_uploaded') . $file_error);

            $upload = new Upload($file);

            $upload->setType();
            if (!$upload->type)
                abort(422, __('nova-media-library::messages.forbidden_file_format') . $file_error);

            $upload->setName($file->getClientOriginalName());

            $upload->setFile();

            if (!$upload->checkSize())
                abort(422, __('nova-media-library::messages.size_limit_exceeded') . $file_error);

            if ($upload->save()) {
                ImageSizes::make($upload->path, $upload->type);
                if ($upload->noResize) {
                    abort(200, __('nova-media-library::messages.unsupported_resize', ['file' => $file->getClientOriginalName()]));
                }
                return;
            }

            abort(422, __('nova-media-library::messages.not_uploaded') . $file_error);
        }


        if(request()->has('folder')) {

            $folder = Helper::getCurrentFolderModel();

            $folderPath =  config('media-library.folder') . '/' .  Str::slug(request()->get('folder'));

            if(Model::where(['type' => 'folder', 'path' => $folderPath])->first()) {
                abort(422,  'This folder already exists');
            }

            mkdir(storage_path('app/public' . $folderPath), 0775);

            Model::create([
                'description' => request()->get('folder'),
                'path' => $folderPath,
                'type' => 'folder',
                'mime' => 'folder',
                'size' => '',
                'folder_id' => $folder->id,
                'created' => now()
            ]);
            return;
        }
	}

	/** Delete all selected files */
	function delete()
	{
		$valid = Validator::make(request()->only('ids'), ['ids' => 'required|array']);
		if ( $valid->fails() ) abort(422, __('nova-media-library::messages.variable_ids_array'));

		$get = Model::find(request('ids'));
		$delete = $this->model->deleteByIds(request('ids'));

		if ( count($get) > 0 ) {
			$sizes = config('media-library.image_sizes.labels');
			$array = [];
			foreach ($get as $key) {
				$array[] = Helper::getFolder($key->path);
				if ( is_array($sizes) ) {
					foreach (array_keys($sizes) as $size) {
						$array[] = Helper::getFolder(Helper::parseSize($key->path, $size));
					}
				}
			}

			Helper::storage()->delete($array);
		}

		return [ 'status' => !!$delete ];
	}

	/** Update description of media file */
	function update()
	{
		$valid = Validator::make(request()->all(), [
			'id' => 'required|numeric',
			'description' => 'required|string|max:250'
		]);
		if ( $valid->fails() ) abort(422, __('nova-media-library::messages.id_desc_incorrect'));

		$this->model->updateData(request('id'), [ 'description' => request('description') ]);
		return [ 'message' => __('nova-media-library::messages.successfully_updated') ];
	}


	/** Crop image from frontend */
	function crop()
	{
		$crop = new Crop(request()->toArray());
		if ( !$crop->form )
			abort(422, __('nova-media-library::messages.crop_disabled'));

		if ( !$crop->check() )
			abort(422, __('nova-media-library::messages.invalid_request'));

		$crop->make();

		$crop->setSize();

		if ( $crop->save() ) return;

		abort(422, __('nova-media-library::messages.not_uploaded'));
	}
}
