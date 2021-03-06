<?php

namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use YoHang88\LetterAvatar\LetterAvatar;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $email
 * @property string $auth_key
 * @property string $login_key For WS Server authentication
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * 
 * @property string $password write-only password
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_BANNED = 5;
    const STATUS_ACTIVE = 10;

    public $password;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username', 'email'], 'required'],
            ['password', 'required', 'on' => 'create'],
            ['password', 'safe', 'on' => 'update'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Логин',
            'password' => 'Пароль',
            'email' => 'Email',
            'status' => 'Статус',
            'created_at' => 'Создан',
            'updated_at' => 'Изменен',
        ];
    }

    /**
     * @return array
     */
    public static function getStatusesArray()
    {
        return [
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_BANNED => 'Забанен',
            self::STATUS_DELETED => 'Удален',
        ];
    }

    /**
     * @return string
     */
    public function getStatusLabel()
    {
        return self::getStatusesArray()[$this->status];
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => [self::STATUS_ACTIVE, self::STATUS_BANNED]]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => [self::STATUS_ACTIVE, self::STATUS_BANNED]]);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * @inheritdoc
     */
    public function getLoginKey()
    {
        return $this->login_key;
    }

    /**
     * @inheritdoc
     */
    public function validateLoginKey($loginKey)
    {
        return $this->getLoginKey() === $loginKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateLoginKey()
    {
        $this->login_key = Yii::$app->security->generateRandomString();
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert or (isset($changedAttributes['username']) and $this->username != $changedAttributes['username'])) {
            $this->generateAvatar();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Generate avatar for user
     */
    public function generateAvatar()
    {
        $avatar = new LetterAvatar($this->username, 'square');
        $avatar->generate()->save(Yii::getAlias('@webroot/uploads/avatars/' . $this->id . '.jpg'));
    }

    /**
     * Get user avatar src or generate new one (for <img> tag)
     *
     * @return string
     */
    public function getAvatarSrc()
    {
        $filename = Yii::getAlias('@web/uploads/avatars/' . $this->id . '.jpg');

        if (file_exists(Yii::getAlias('@webroot/uploads/avatars/' . $this->id . '.jpg'))) {
            return $filename;
        } else {
            $this->generateAvatar();
            return $this->getAvatarSrc();
        }
    }
}
